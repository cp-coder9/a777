<?php
declare(strict_types=1);
namespace Architex\Platform\Feedback;
use Architex\Platform\Identity\Identifier;
use PDO;
use PDOException;
final readonly class FeedbackService
{
    private const SELECT = "SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) projectId,category,message,context,status,client_request_id clientRequestId,created_at createdAt,updated_at updatedAt FROM v1_feedback_records WHERE organisation_id=UNHEX(:org) AND user_id=UNHEX(:user)";
    public function __construct(private PDO $pdo) {}
    public function list(string $organisationId,string $userId): array
    {
        $this->assertMember($organisationId,$userId);
        $statement=$this->pdo->prepare(self::SELECT.' ORDER BY created_at DESC,id DESC');
        $statement->execute(['org'=>$organisationId,'user'=>$userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    public function find(string $organisationId,string $userId,string $id): array
    {
        if (preg_match('/\A[a-f0-9]{32}\z/i',$id)!==1) throw new FeedbackException('feedback_not_found',404);
        // The record scope is always BOTH organisation and submitting user.
        $statement=$this->pdo->prepare(self::SELECT.' AND id=UNHEX(:id)');
        $statement->execute(['org'=>$organisationId,'user'=>$userId,'id'=>$id]);
        $row=$statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new FeedbackException('feedback_not_found',404);
        $this->assertMember($organisationId,$userId);
        return $row;
    }
    public function create(string $organisationId,string $userId,FeedbackInput $input): array
    {
        $this->assertMember($organisationId,$userId);
        if ($input->projectId!==null) {
            // Same project visibility rule as ProjectPassportService: active org membership.
            $project=$this->pdo->prepare('SELECT id FROM projects WHERE id=UNHEX(:project) AND organisation_id=UNHEX(:org)');
            $project->execute(['project'=>$input->projectId,'org'=>$organisationId]);
            if ($project->fetchColumn()===false) throw new FeedbackException('project_not_found',404);
        }
        if ($input->clientRequestId!==null && ($existing=$this->retryRecord($organisationId,$userId,$input))!==null) return $existing;
        $id=Identifier::generate();
        $insert=$this->pdo->prepare("INSERT INTO v1_feedback_records(id,organisation_id,user_id,project_id,category,message,context,status,client_request_id,created_at,updated_at) VALUES(UNHEX(:id),UNHEX(:org),UNHEX(:user),UNHEX(:project),:category,:message,:context,'Received',:request,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        try {
            $insert->execute(['id'=>$id,'org'=>$organisationId,'user'=>$userId,'project'=>$input->projectId,'category'=>$input->category,'message'=>$input->message,'context'=>$input->context,'request'=>$input->clientRequestId]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1]??null)===1062 && $input->clientRequestId!==null && ($existing=$this->retryRecord($organisationId,$userId,$input))!==null) return $existing;
            throw $e;
        }
        return $this->find($organisationId,$userId,$id);
    }
    private function retryRecord(string $org,string $user,FeedbackInput $input): ?array
    {
        $statement=$this->pdo->prepare(self::SELECT.' AND client_request_id=:request');
        $statement->execute(['org'=>$org,'user'=>$user,'request'=>$input->clientRequestId]);
        $row=$statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        if ($row['category']!==$input->category || $row['message']!==$input->message || $row['context']!==$input->context || $row['projectId']!==$input->projectId) throw new FeedbackException('feedback_request_conflict',409);
        return $row;
    }
    private function assertMember(string $org,string $user): void
    {
        if (preg_match('/\A[a-f0-9]{32}\z/i',$org)!==1 || preg_match('/\A[a-f0-9]{32}\z/i',$user)!==1) throw new FeedbackException('feedback_not_found',404);
        $statement=$this->pdo->prepare("SELECT 1 FROM organisation_memberships WHERE organisation_id=UNHEX(:org) AND user_id=UNHEX(:user) AND status='active'");
        $statement->execute(['org'=>$org,'user'=>$user]);
        if ($statement->fetchColumn()===false) throw new FeedbackException('feedback_not_found',404);
    }
}
