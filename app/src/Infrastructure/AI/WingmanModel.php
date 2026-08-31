<?php

declare(strict_types=1);

namespace Architex\Infrastructure\AI;

interface WingmanModel
{
    /**
     * @param list<array{role:string,content:string}> $messages
     * @param list<array<string,mixed>> $tools
     * @return array{content:string,toolCalls:list<array{id:string,name:string,arguments:array<string,mixed>}>,requestId:?string,model:string,finishReason:?string,usage:array{input:int,output:int}}
     */
    public function complete(array $messages, array $tools = []): array;
}
