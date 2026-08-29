<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;

final readonly class BuildInfoController
{
    public function __construct(private string$releaseMetadataPath){}

    public function __invoke(Request$request):Response
    {
        if(!is_file($this->releaseMetadataPath))return Response::success(['available'=>false,'version'=>null,'branch'=>'working-tree','sourceCommit'=>null,'builtAt'=>null,'php'=>'8.3','apiMode'=>'php-shared-hosting','reviewableArtifact'=>false]);
        try{$metadata=json_decode((string)file_get_contents($this->releaseMetadataPath),true,64,JSON_THROW_ON_ERROR);}catch(\Throwable){return Response::success(['available'=>false,'version'=>null,'branch'=>'unknown','sourceCommit'=>null,'builtAt'=>null,'php'=>'8.3','apiMode'=>'php-shared-hosting','reviewableArtifact'=>false]);}
        return Response::success(['available'=>true,'version'=>$metadata['version']??null,'branch'=>$metadata['branch']??'unknown','sourceCommit'=>$metadata['sourceCommit']??null,'builtAt'=>$metadata['builtAt']??null,'php'=>$metadata['php']??'8.3','apiMode'=>$metadata['apiMode']??'php-shared-hosting','reviewableArtifact'=>true]);
    }
}
