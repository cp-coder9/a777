<?php
declare(strict_types=1);
namespace Architex\Platform\Feedback;
final readonly class FeedbackInput
{
    private function __construct(public string $category, public string $message, public string $context, public ?string $projectId, public ?string $clientRequestId) {}
    public static function fromArray(array $body): self
    {
        $category=$body['category']??null;
        if (!in_array($category,['Bug','Feature request','Usability','Praise'],true)) throw new FeedbackException('invalid_category');
        $message=self::text($body['message']??null,10,2000,'invalid_message');
        $context=self::text($body['context']??'',0,512,'invalid_context');
        $project=$body['projectId']??null;
        if ($project!==null && (!is_string($project)||preg_match('/\A[a-f0-9]{32}\z/i',$project)!==1)) throw new FeedbackException('invalid_project_id');
        $key=$body['clientRequestId']??null;
        if ($key!==null && (!is_string($key)||preg_match('/\A[A-Za-z0-9_-]{16,64}\z/',$key)!==1)) throw new FeedbackException('invalid_client_request_id');
        return new self($category,$message,$context,$project===null?null:strtolower($project),$key);
    }
    private static function text(mixed $value,int $min,int $max,string $reason): string
    {
        if (!is_string($value)||!mb_check_encoding($value,'UTF-8')) throw new FeedbackException($reason);
        $value=trim($value);
        if (mb_strlen($value)<$min||mb_strlen($value)>$max) throw new FeedbackException($reason);
        return $value;
    }
}
