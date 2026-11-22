<?php

namespace App\Services\CV;

use App\Enums\CvStatus;
use App\Models\Conversation;
use App\Models\Cv;
use App\Models\CvEducation;
use App\Models\CvPersonalDetail;
use App\Models\CvReference;
use App\Models\CvSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CvDraftService
{
    public function __construct(
        private readonly CvSectionService $sectionService
    ) {}

    /**
     * Move an owned draft CV into the reviewing stage.
     *
     * Only the draft -> reviewing transition is permitted here.
     */
    public function submitForReview(User $user, Cv $cv): Cv
    {
        return DB::transaction(function () use ($user, $cv): Cv {
            $lockedCv = $this->lockDraft($user, $cv);

            $lockedCv->status = CvStatus::REVIEWING;
            $lockedCv->save();

            return $lockedCv;
        }, 3);
    }

    /**
     * Save or partially update personal details.
     */
    public function savePersonalDetails(
        User $user,
        Cv $cv,
        array $data
    ): CvPersonalDetail {
        return $this->sectionService->savePersonalDetails(
            $user,
            $cv,
            $data
        );
    }

    /**
     * Save a repeatable CV record.
     *
     * Null recordId creates a new record.
     * A supplied recordId updates only a record belonging
     * to the specified user's CV.
     */
    public function saveRecord(
        User $user,
        Cv $cv,
        string $section,
        array $data,
        ?int $recordId = null
    ): Model {
        // Education persistence is handled by CvSectionService.
        if ($section === 'educations') {
            return $this->sectionService->saveEducation(
                $user,
                $cv,
                $data,
                $recordId
            );
        }

        // Working experience persistence is handled by CvSectionService.
        if ($section === 'experiences') {
            return $this->sectionService->saveExperience(
                $user,
                $cv,
                $data,
                $recordId
            );
        }

        // Skill persistence is handled by CvSectionService.
        if ($section === 'skills') {
            return $this->sectionService->saveSkill(
                $user,
                $cv,
                $data,
                $recordId
            );
        }

        // Project persistence is handled by CvSectionService.
        if ($section === 'projects') {
            return $this->sectionService->saveProject(
                $user,
                $cv,
                $data,
                $recordId
            );
        }

        // Certification persistence is handled by CvSectionService.
        if ($section === 'certifications') {
            return $this->sectionService->saveCertification(
                $user,
                $cv,
                $data,
                $recordId
            );
        }

        // Language persistence is handled by CvSectionService.
        if ($section === 'languages') {
            return $this->sectionService->saveLanguage(
                $user,
                $cv,
                $data,
                $recordId
            );
        }

        // Reference persistence is handled by CvSectionService.
        if ($section === 'references') {
            return $this->sectionService->saveReference(
                $user,
                $cv,
                $data,
                $recordId
            );
        }

        throw new InvalidArgumentException(
            'Unsupported CV section.'
        );
    }

    /**
     * Save or update one optional section of a draft CV.
     */
    public function saveOptionalSection(
        User $user,
        Cv $cv,
        array $data
    ): CvSection {
        return $this->sectionService->saveOptionalSection(
            $user,
            $cv,
            $data
        );
    }

    /**
     * Retrieve all draft CVs belonging to the supplied user.
     */
    public function listDrafts(User $user): Collection
    {
        return $user->cvs()
            ->where('status', CvStatus::DRAFT->value)
            ->latest('updated_at')
            ->get([
                'id',
                'name',
                'status',
                'created_at',
                'updated_at',
            ]);
    }

    /**
     * Resume an existing draft in a new conversation.
     */
    public function resume(
        User $user,
        Conversation $conversation,
        int $cvId
    ): Cv {
        return DB::transaction(function () use (
            $user,
            $conversation,
            $cvId
        ): Cv {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->getKey())
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $cv = $user->cvs()
                ->whereKey($cvId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cv->status !== CvStatus::DRAFT) {
                throw new InvalidArgumentException(
                    'Only draft CVs can be resumed.'
                );
            }

            $lockedConversation->active_cv_id = $cv->id;
            $lockedConversation->save();

            return $cv;
        }, 3);
    }

    /**
     * Lock a CV belonging to the supplied user and ensure
     * that it is still a draft.
     */
    private function lockDraft(User $user, Cv $cv): Cv
    {
        $lockedCv = $user->cvs()
            ->whereKey($cv->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($lockedCv->status !== CvStatus::DRAFT) {
            throw new RuntimeException(
                'Only CV drafts can be modified.'
            );
        }

        return $lockedCv;
    }

    /**
     * Keep only model-approved fields and prevent callers
     * from changing the CV foreign key.
     */
    private function prepareAttributes(
        Model $model,
        array $data
    ): array {
        $allowedFields = array_diff(
            $model->getFillable(),
            ['cv_id']
        );

        $unknownFields = array_diff(
            array_keys($data),
            $allowedFields
        );

        if ($unknownFields !== []) {
            throw new InvalidArgumentException(
                'The supplied data contains unsupported fields: '
                . implode(', ', $unknownFields)
            );
        }

        if ($data === []) {
            throw new InvalidArgumentException(
                'At least one field must be supplied.'
            );
        }

        return $data;
    }
}