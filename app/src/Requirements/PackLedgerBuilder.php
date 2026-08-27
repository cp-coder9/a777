<?php

declare(strict_types=1);

namespace Architex\Requirements;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final class PackLedgerBuilder
{
    private const EXTENSIONS = ['md', 'txt', 'json', 'ts', 'tsx'];
    private int $totalBytes = 0;

    public function __construct(private readonly PackLedgerLimits $limits = new PackLedgerLimits()) {}

    /** @return array{schemaVersion:int,sources:list<array<string,mixed>>,requirements:list<array<string,mixed>>} */
    public function build(string $packsDir): array
    {
        $this->totalBytes = 0;
        $sources = []; $requirements = [];
        foreach ($this->discover($packsDir) as $source) {
            $content = $this->read($source);
            $sources[] = ['pack' => $source['pack'], 'relativePath' => $source['relativePath'], 'sha256' => hash('sha256', $content)];
            foreach ($this->statements($content, $source['extension']) as $index => $statement) {
                if (count($requirements) >= $this->limits->maximumRequirements) throw new RuntimeException('Maximum requirement count exceeded.');
                $ordinal = $index + 1;
                $requirements[] = ['id' => 'PACK-' . substr(hash('sha256', $source['relativePath'] . "\n" . $ordinal . "\n" . $statement), 0, 16), 'pack' => $source['pack'], 'source' => $source['relativePath'], 'ordinal' => $ordinal, 'statement' => $statement, 'status' => 'not_implemented', 'release' => null, 'implementation' => [], 'evidence' => []];
            }
            unset($content);
        }
        usort($requirements, static fn(array $a, array $b): int => [$a['source'], $a['ordinal']] <=> [$b['source'], $b['ordinal']]);
        return ['schemaVersion' => 1, 'sources' => $sources, 'requirements' => $requirements];
    }

    /** @param array{schemaVersion:int,sources:list<array<string,mixed>>,requirements:list<array<string,mixed>>} $ledger @return iterable<string> */
    public function jsonChunks(array $ledger): iterable
    {
        yield "{\n  \"schemaVersion\": 1,\n  \"sources\": [\n";
        foreach ($ledger['sources'] as $i => $source) yield '    ' . json_encode($source, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ($i === array_key_last($ledger['sources']) ? "\n" : ",\n");
        yield "  ],\n  \"requirements\": [\n";
        foreach ($ledger['requirements'] as $i => $requirement) yield '    ' . json_encode($requirement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ($i === array_key_last($ledger['requirements']) ? "\n" : ",\n");
        yield "  ]\n}\n";
    }

    /** @param array{schemaVersion:int,sources:list<array<string,mixed>>,requirements:list<array<string,mixed>>} $ledger @return iterable<string> */
    public function markdownChunks(array $ledger): iterable
    {
        yield "# Architex Pack Requirements Ledger\n\nGenerated deterministically from the supplied pack files and ZIP entries. Source claims do not change implementation status.\n\n## Summary\n\n";
        yield '- Schema version: 1' . "\n- Sources: " . count($ledger['sources']) . "\n- Requirements: " . count($ledger['requirements']) . "\n- Implemented requirements: 0\n\n## Sources\n\n";
        foreach ($ledger['sources'] as $source) yield '- `' . $source['relativePath'] . "`\n  - pack: " . $source['pack'] . "\n  - sha256: `" . $source['sha256'] . "`\n";
        yield "\n## Requirements\n\n";
        foreach ($ledger['requirements'] as $i => $r) {
            yield '### ' . $r['id'] . "\n\n- status: not_implemented\n- pack: " . $r['pack'] . "\n- source: `" . $r['source'] . "`\n- ordinal: " . $r['ordinal'] . "\n- statement: " . str_replace("\n", ' ', $r['statement']) . "\n- release: null\n- implementation: []\n- evidence: []\n";
            if ($i !== array_key_last($ledger['requirements'])) yield "\n";
        }
    }

    /** @return list<array<string,mixed>> */
    private function discover(string $packsDir): array
    {
        $base = realpath($packsDir);
        if ($base === false || !is_dir($base)) throw new RuntimeException("Packs directory does not exist: $packsDir");
        $directory = new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator($directory, static fn($entry): bool => !$entry->isDir() || $entry->getFilename() !== 'node_modules');
        $found = [];
        foreach (new RecursiveIteratorIterator($filtered) as $file) {
            if (!$file->isFile()) continue;
            $relative = $this->relative($base, $file->getPathname()); $extension = strtolower($file->getExtension());
            if (in_array($extension, self::EXTENSIONS, true)) {
                $this->allowBytes($file->getSize(), $relative);
                $found[] = ['kind'=>'file','path'=>$file->getPathname(),'pack'=>$this->pack($relative),'relativePath'=>$relative,'extension'=>$extension,'bytes'=>$file->getSize()];
            } elseif ($extension === 'zip') array_push($found, ...$this->zipSources($file->getPathname(), $relative));
        }
        usort($found, static fn(array $a,array $b): int => $a['relativePath'] <=> $b['relativePath']);
        return $found;
    }

    /** @return list<array<string,mixed>> */
    private function zipSources(string $path, string $relativeArchive): array
    {
        $zip = new ZipArchive(); if ($zip->open($path) !== true) throw new RuntimeException('Unable to open pack archive: ' . basename($relativeArchive));
        try {
            if ($zip->numFiles > $this->limits->maximumZipEntries) throw new RuntimeException('Maximum ZIP entry count exceeded.');
            $found=[];
            for($i=0;$i<$zip->numFiles;$i++) {
                $entry=$zip->statIndex($i); if($entry===false || str_ends_with($entry['name'],'/')) continue;
                $extension=strtolower(pathinfo($entry['name'],PATHINFO_EXTENSION)); if($this->nodeModule($entry['name']) || !in_array($extension,self::EXTENSIONS,true)) continue;
                $bytes=(int)$entry['size']; if($bytes>$this->limits->maximumZipEntryBytes) throw new RuntimeException('Maximum ZIP entry uncompressed bytes exceeded.');
                if($bytes/max(1,(int)$entry['comp_size'])>$this->limits->maximumCompressionRatio) throw new RuntimeException('Maximum ZIP compression ratio exceeded.');
                $relative=$relativeArchive.'!/'.str_replace('\\','/',$entry['name']); $this->allowBytes($bytes,$relative);
                $found[]=['kind'=>'zip','path'=>$path,'entry'=>$i,'pack'=>$this->zipPack($relativeArchive,$entry['name']),'relativePath'=>$relative,'extension'=>$extension,'bytes'=>$bytes];
            }
            return $found;
        } finally { $zip->close(); }
    }

    /** @param array<string,mixed> $source */
    private function read(array $source): string
    {
        if($source['kind']==='file') $content=file_get_contents($source['path']);
        else { $zip=new ZipArchive(); if($zip->open($source['path'])!==true) throw new RuntimeException('Unable to open pack archive: '.$source['relativePath']); try{$content=$zip->getFromIndex($source['entry']);}finally{$zip->close();} }
        if($content===false || strlen($content)!==$source['bytes']) throw new RuntimeException('Unable to read pack source: '.$source['relativePath']);
        return $content;
    }
    private function allowBytes(int $bytes,string $path): void { if($bytes>$this->limits->perSourceBytes) throw new RuntimeException("Per-source byte limit exceeded: $path"); $this->totalBytes+=$bytes; if($this->totalBytes>$this->limits->maximumTotalInputBytes) throw new RuntimeException('Maximum total input bytes exceeded.'); }
    /** @return iterable<string> */
    private function statements(string $content,string $extension): iterable
    {
        $content=str_replace(["\r\n","\r"],"\n",preg_replace('/^\xEF\xBB\xBF/','',$content)??$content);
        if($extension==='json') { yield from $this->jsonStatements($content); return; }
        $paragraph=[];
        foreach(explode("\n",$content) as $line){
            $line=trim(preg_replace('/\s+/',' ',$line)??'');
            if($line===''){if($paragraph!==[]){yield implode(' ',$paragraph);}$paragraph=[];continue;}
            if(preg_match('/^(#{1,6}\s+|[-*+]\s+|\d+[.)]\s+|>\s+)/',$line)===1||(in_array($extension,['ts','tsx'],true)&&preg_match('/^(export\s+)?(abstract\s+)?(class|interface|type|enum|function|const|let)\b/',$line)===1)){
                if($paragraph!==[]){yield implode(' ',$paragraph);}$paragraph=[];yield $line;continue;
            }
            $paragraph[]=$line;
        }
        if($paragraph!==[]) yield implode(' ',$paragraph);
    }
    /** @return iterable<string> */ private function jsonStatements(string $content): iterable {try{$decoded=json_decode($content,true,flags:JSON_THROW_ON_ERROR);}catch(\JsonException){yield from $this->statements($content,'txt');return;}yield from $this->jsonWalk($decoded,'$');}
    /** @return iterable<string> */ private function jsonWalk(mixed $value,string $path,?string $key=null):iterable {if($key!==null&&$this->semantic($key)){yield $path.': '.json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);return;}if(is_array($value))foreach($value as $k=>$v)yield from $this->jsonWalk($v,is_int($k)?$path.'['.$k.']':$path.'.'.$k,is_int($k)?null:$k);}
    private function relative(string $base,string $path):string{return str_replace('\\','/',substr($path,strlen($base)+1));} private function pack(string $relative):string{return explode('/',$relative,2)[0];} private function zipPack(string $archive,string $entry):string{$entry=str_replace('\\','/',$entry);return str_contains($entry,'/')?explode('/',$entry,2)[0]:pathinfo(basename($archive),PATHINFO_FILENAME);} private function nodeModule(string $path):bool{return preg_match('#(?:^|/)node_modules(?:/|$)#',str_replace('\\','/',$path))===1;} private function semantic(string $key):bool{return preg_match('/^(?:requirements?|acceptance_?criteria|roles?|permissions?|actions?|routes?|states?|status(?:es)?|outputs?|guardrails?|modules?|tools?|workflows?|features?|entities|events?|services?|screens?|pages?|apis?|endpoints?)$/i',$key)===1;}
}
