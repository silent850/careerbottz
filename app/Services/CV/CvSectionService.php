<?php

namespace App\Services\CV;

use App\Enums\CvStatus;
use App\Models\Cv;
use App\Models\CvEducation;
use App\Models\CvPersonalDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class CvSectionService
{
    /**
     * Validate personal details.
     *
     * A new personal-details record requires a full name.
     * Existing records support partial updates.
     */
    public function validatePersonalDetails(
        array $data,
        bool $isNewRecord
    ): array {
        $rules = [
            'full_name' => $this->requiredString(255, $isNewRecord),

            'professional_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:50'],
            'marital_status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Validate repeatable CV section records.
     *
     * Supported sections correspond to the existing CV tables.
     */
    public function validateRecord(
        string $section,
        array $data,
        bool $isNewRecord
    ): array {
        $rules = match ($section) {
            'educations' => [
                'institution' => $this->requiredString(255, $isNewRecord),
                'qualification' => $this->requiredString(255, $isNewRecord),
                'field_of_study' => ['sometimes', 'nullable', 'string', 'max:255'],
                'start_date' => ['sometimes', 'nullable', 'date'],
                'end_date' => ['sometimes', 'nullable', 'date'],
                'grade' => ['sometimes', 'nullable', 'string', 'max:100'],
                'description' => ['sometimes', 'nullable', 'string'],
            ],

            'experiences' => [
                'employer' => $this->requiredString(255, $isNewRecord),
                'job_title' => $this->requiredString(255, $isNewRecord),
                'location' => ['sometimes', 'nullable', 'string', 'max:255'],
                'start_date' => ['sometimes', 'nullable', 'date'],
                'end_date' => ['sometimes', 'nullable', 'date'],
                'is_current' => ['sometimes', 'boolean'],
                'responsibilities' => ['sometimes', 'nullable', 'string'],
                'achievements' => ['sometimes', 'nullable', 'string'],
            ],

            'skills' => [
                'skill_name' => $this->requiredString(255, $isNewRecord),
                'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            ],

            'projects' => [
                'project_name' => $this->requiredString(255, $isNewRecord),
                'description' => ['sometimes', 'nullable', 'string'],
                'project_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
                'start_date' => ['sometimes', 'nullable', 'date'],
                'end_date' => ['sometimes', 'nullable', 'date'],
            ],

            'certifications' => [
                'certification_name' => $this->requiredString(255, $isNewRecord),
                'issuing_organization' => ['sometimes', 'nullable', 'string', 'max:255'],
                'issue_date' => ['sometimes', 'nullable', 'date'],
                'expiry_date' => ['sometimes', 'nullable', 'date'],
                'credential_id' => ['sometimes', 'nullable', 'string', 'max:255'],
                'credential_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            ],

            'languages' => [
                'language' => $this->requiredString(100, $isNewRecord),
                'proficiency_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            ],

            'references' => [
                'full_name' => $this->requiredString(255, $isNewRecord),
                'professional_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'organization' => ['sometimes', 'nullable', 'string', 'max:255'],
                'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
                'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
                'relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            ],

            default => throw new InvalidArgumentException(
                'Unsupported CV section.'
            ),
        };

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Validate a professional summary.
     */
    public function validateProfessionalSummary(
        ?string $summary
    ): ?string {
        $validated = Validator::make(
            ['professional_summary' => $summary],
            [
                'professional_summary' => ['nullable', 'string'],
            ]
        )->validate();

        return $validated['professional_summary'];
    }

    /**
     * Save or update the professional summary for an owned draft CV.
     */
    public function saveProfessionalSummary(
        User $user,
        Cv $cv,
        ?string $summary
    ): Cv {
        $validatedSummary = $this->validateProfessionalSummary($summary);

        return DB::transaction(function () use (
            $user,
            $cv,
            $validatedSummary
        ): Cv {
            $lockedCv = $user->cvs()
                ->whereKey($cv->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCv->status !== CvStatus::DRAFT) {
                throw new InvalidArgumentException(
                    'Only draft CVs can be updated.'
                );
            }

            $lockedCv->professional_summary = $validatedSummary;
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
        $allowedFields = array_diff(
            (new CvPersonalDetail())->getFillable(),
            ['cv_id']
        );

        $unknownFields = array_diff(
            array_keys($data),
            $allowedFields
        );

        if ($unknownFields !== []) {
            throw new InvalidArgumentException(
                'Unsupported personal details fields: ' .
                implode(', ', $unknownFields)
            );
        }

        if ($data === []) {
            throw new InvalidArgumentException(
                'At least one personal details field must be provided.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $cv,
            $data
        ): CvPersonalDetail {
            $lockedCv = $user->cvs()
                ->whereKey($cv->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCv->status !== CvStatus::DRAFT) {
                throw new InvalidArgumentException(
                    'Only draft CVs can be updated.'
                );
            }

            $existingDetails = $lockedCv->personalDetails()
                ->lockForUpdate()
                ->first();

            $validatedData = $this->validatePersonalDetails(
                $data,
                $existingDetails === null
            );

            if ($existingDetails !== null) {
                $existingDetails->fill($validatedData);
                $existingDetails->save();

                return $existingDetails;
            }

            return $lockedCv->personalDetails()->create(
                $validatedData
            );
        }, 3);
    }

    /**
     * Save a new education record or update an existing record
     * belonging to the specified user's draft CV.
     *
     * A null record ID creates a new education record.
     * A supplied record ID updates only that CV's record.
     */
    public function saveEducation(
        User $user,
        Cv $cv,
        array $data,
        ?int $recordId = null
    ): CvEducation {
        $allowedFields = array_diff(
            (new CvEducation())->getFillable(),
            ['cv_id']
        );

        $unknownFields = array_diff(
            array_keys($data),
            $allowedFields
        );

        if ($unknownFields !== []) {
            throw new InvalidArgumentException(
                'Unsupported education fields: ' .
                implode(', ', $unknownFields)
            );
        }

        if ($data === []) {
            throw new InvalidArgumentException(
                'At least one education field must be provided.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $cv,
            $data,
            $recordId
        ): CvEducation {
            $lockedCv = $user->cvs()
                ->whereKey($cv->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCv->status !== CvStatus::DRAFT) {
                throw new InvalidArgumentException(
                    'Only draft CVs can be updated.'
                );
            }

            $validatedData = $this->validateRecord(
                'educations',
                $data,
                $recordId === null
            );

            $relation = $lockedCv->educations();

            if ($recordId !== null) {
                $education = $relation
                    ->whereKey($recordId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $education->fill($validatedData);
                $education->save();

                return $education;
            }

            return $relation->create($validatedData);
        }, 3);
    }

    /**
     * Required fields are enforced when creating a record.
     * On updates, they are required only when supplied.
     */
    private function requiredString(
        int $maxLength,
        bool $isNewRecord
    ): array {
        return $isNewRecord
            ? ['required', 'string', 'max:' . $maxLength]
            : ['sometimes', 'required', 'string', 'max:' . $maxLength];
    }
}
