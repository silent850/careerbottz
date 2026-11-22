<?php

namespace App\Services\CV;

use App\Enums\CvStatus;
use App\Models\Cv;
use App\Models\CvEducation;
use App\Models\CvExperience;
use App\Models\CvSkill;
use App\Models\CvProject;
use App\Models\CvPersonalDetail;
use App\Models\CvCertification;
use App\Models\CvLanguage;
use App\Models\User;
use App\Models\CvReference;
use App\Models\CvSection;
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
     * Save a new working experience record or update an existing record
     * belonging to the specified user's draft CV.
     *
     * A null record ID creates a new record.
     * A supplied record ID updates only that CV's record.
     */
    public function saveExperience(
        User $user,
        Cv $cv,
        array $data,
        ?int $recordId = null
    ): CvExperience {
        $allowedFields = array_diff(
            (new CvExperience())->getFillable(),
            ['cv_id']
        );

        $unknownFields = array_diff(
            array_keys($data),
            $allowedFields
        );

        if ($unknownFields !== []) {
            throw new InvalidArgumentException(
                'Unsupported experience fields: ' .
                implode(', ', $unknownFields)
            );
        }

        if ($data === []) {
            throw new InvalidArgumentException(
                'At least one experience field must be provided.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $cv,
            $data,
            $recordId
        ): CvExperience {
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
                'experiences',
                $data,
                $recordId === null
            );

            $relation = $lockedCv->experiences();

            if ($recordId !== null) {
                $experience = $relation
                    ->whereKey($recordId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $experience->fill($validatedData);
                $experience->save();

                return $experience;
            }

            return $relation->create($validatedData);
        }, 3);
    }

    /**
     * Required fields are enforced when creating a record.
     * On updates, they are required only when supplied.
     */

    /**
 * Save a skill belonging to the specified user's draft CV.
 *
 * A null record ID creates a new skill record.
 * A supplied record ID updates only that CV's skill.
 */
public function saveSkill(
    User $user,
    Cv $cv,
    array $data,
    ?int $recordId = null
): CvSkill {
    $allowedFields = array_diff(
        (new CvSkill())->getFillable(),
        ['cv_id']
    );

    $unknownFields = array_diff(
        array_keys($data),
        $allowedFields
    );

    if ($unknownFields !== []) {
        throw new InvalidArgumentException(
            'Unsupported skill fields: ' .
            implode(', ', $unknownFields)
        );
    }

    if ($data === []) {
        throw new InvalidArgumentException(
            'At least one skill field must be provided.'
        );
    }

    return DB::transaction(function () use (
        $user,
        $cv,
        $data,
        $recordId
    ): CvSkill {
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
            'skills',
            $data,
            $recordId === null
        );

        $relation = $lockedCv->skills();

        if ($recordId !== null) {
            $skill = $relation
                ->whereKey($recordId)
                ->lockForUpdate()
                ->firstOrFail();

            $skill->fill($validatedData);
            $skill->save();

            return $skill;
        }

        return $relation->create($validatedData);
    }, 3);
}
    /**
 * Save a new project record or update an existing project
 * belonging to the specified user's draft CV.
 *
 * A null record ID creates a new project record.
 * A supplied record ID updates only that CV's project.
 */
public function saveProject(
    User $user,
    Cv $cv,
    array $data,
    ?int $recordId = null
): CvProject {
    $allowedFields = array_diff(
        (new CvProject())->getFillable(),
        ['cv_id']
    );

    $unknownFields = array_diff(
        array_keys($data),
        $allowedFields
    );

    if ($unknownFields !== []) {
        throw new InvalidArgumentException(
            'Unsupported project fields: ' .
            implode(', ', $unknownFields)
        );
    }

    if ($data === []) {
        throw new InvalidArgumentException(
            'At least one project field must be provided.'
        );
    }

    return DB::transaction(function () use (
        $user,
        $cv,
        $data,
        $recordId
    ): CvProject {
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
            'projects',
            $data,
            $recordId === null
        );

        $relation = $lockedCv->projects();

        if ($recordId !== null) {
            $project = $relation
                ->whereKey($recordId)
                ->lockForUpdate()
                ->firstOrFail();

            $project->fill($validatedData);
            $project->save();

            return $project;
        }

        return $relation->create($validatedData);
    }, 3);
}
/**
 * Save a new certification record or update an existing certification
 * belonging to the specified user's draft CV.
 *
 * A null record ID creates a new certification record.
 * A supplied record ID updates only that CV's certification.
 */
public function saveCertification(
    User $user,
    Cv $cv,
    array $data,
    ?int $recordId = null
): CvCertification {
    $allowedFields = array_diff(
        (new CvCertification())->getFillable(),
        ['cv_id']
    );

    $unknownFields = array_diff(
        array_keys($data),
        $allowedFields
    );

    if ($unknownFields !== []) {
        throw new InvalidArgumentException(
            'Unsupported certification fields: ' .
            implode(', ', $unknownFields)
        );
    }

    if ($data === []) {
        throw new InvalidArgumentException(
            'At least one certification field must be provided.'
        );
    }

    return DB::transaction(function () use (
        $user,
        $cv,
        $data,
        $recordId
    ): CvCertification {
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
            'certifications',
            $data,
            $recordId === null
        );

        $relation = $lockedCv->certifications();

        if ($recordId !== null) {
            $certification = $relation
                ->whereKey($recordId)
                ->lockForUpdate()
                ->firstOrFail();

            $certification->fill($validatedData);
            $certification->save();

            return $certification;
        }

        return $relation->create($validatedData);
    }, 3);
}
/**
 * Save a new language record or update an existing language
 * belonging to the specified user's draft CV.
 *
 * A null record ID creates a new language record.
 * A supplied record ID updates only that CV's language.
 */
public function saveLanguage(
    User $user,
    Cv $cv,
    array $data,
    ?int $recordId = null
): CvLanguage {
    $allowedFields = array_diff(
        (new CvLanguage())->getFillable(),
        ['cv_id']
    );

    $unknownFields = array_diff(
        array_keys($data),
        $allowedFields
    );

    if ($unknownFields !== []) {
        throw new InvalidArgumentException(
            'Unsupported language fields: ' .
            implode(', ', $unknownFields)
        );
    }

    if ($data === []) {
        throw new InvalidArgumentException(
            'At least one language field must be provided.'
        );
    }

    return DB::transaction(function () use (
        $user,
        $cv,
        $data,
        $recordId
    ): CvLanguage {
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
            'languages',
            $data,
            $recordId === null
        );

        $relation = $lockedCv->languages();

        if ($recordId !== null) {
            $language = $relation
                ->whereKey($recordId)
                ->lockForUpdate()
                ->firstOrFail();

            $language->fill($validatedData);
            $language->save();

            return $language;
        }

        return $relation->create($validatedData);
    }, 3);
}

/**
 * Save a new reference or update an existing reference
 * belonging to the specified user's draft CV.
 *
 * A null record ID creates a new reference.
 * A supplied record ID updates only that CV's reference.
 */
public function saveReference(
    User $user,
    Cv $cv,
    array $data,
    ?int $recordId = null
): CvReference {
    $allowedFields = array_diff(
        (new CvReference())->getFillable(),
        ['cv_id']
    );

    $unknownFields = array_diff(
        array_keys($data),
        $allowedFields
    );

    if ($unknownFields !== []) {
        throw new InvalidArgumentException(
            'Unsupported reference fields: ' .
            implode(', ', $unknownFields)
        );
    }

    if ($data === []) {
        throw new InvalidArgumentException(
            'At least one reference field must be provided.'
        );
    }

    return DB::transaction(function () use (
        $user,
        $cv,
        $data,
        $recordId
    ): CvReference {
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
            'references',
            $data,
            $recordId === null
        );

        $relation = $lockedCv->references();

        if ($recordId !== null) {
            $reference = $relation
                ->whereKey($recordId)
                ->lockForUpdate()
                ->firstOrFail();

            $reference->fill($validatedData);
            $reference->save();

            return $reference;
        }

        return $relation->create($validatedData);
    }, 3);
}

/**
 * Save or update one optional section belonging to the
 * specified user's draft CV.
 *
 * The section key identifies the section within this CV.
 * Updating an existing section changes only that section.
 */
public function saveOptionalSection(
    User $user,
    Cv $cv,
    array $data
): CvSection {
    $allowedFields = array_diff(
        (new CvSection())->getFillable(),
        ['cv_id']
    );

    $unknownFields = array_diff(
        array_keys($data),
        $allowedFields
    );

    if ($unknownFields !== []) {
        throw new InvalidArgumentException(
            'Unsupported optional section fields: ' .
            implode(', ', $unknownFields)
        );
    }

    if (
        ! array_key_exists('section_key', $data) ||
        ! is_string($data['section_key']) ||
        trim($data['section_key']) === ''
    ) {
        throw new InvalidArgumentException(
            'A section key must be provided.'
        );
    }

    return DB::transaction(function () use (
        $user,
        $cv,
        $data
    ): CvSection {
        $lockedCv = $user->cvs()
            ->whereKey($cv->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($lockedCv->status !== CvStatus::DRAFT) {
            throw new InvalidArgumentException(
                'Only draft CVs can be updated.'
            );
        }

        $relation = $lockedCv->sections();

        $existingSection = $relation
            ->where('section_key', $data['section_key'])
            ->lockForUpdate()
            ->first();

        $isNewSection = $existingSection === null;

        $rules = [
            'section_key' => ['required', 'string', 'max:100'],
            'section_title' => $isNewSection
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],
            'content' => $isNewSection
                ? ['required', 'array']
                : ['sometimes', 'required', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_visible' => ['sometimes', 'boolean'],
        ];

        $validatedData = Validator::make(
            $data,
            $rules
        )->validate();

        if ($existingSection !== null) {
            $existingSection->fill($validatedData);
            $existingSection->save();

            return $existingSection;
        }

        return $relation->create($validatedData);
    }, 3);
}
    private function requiredString(
        int $maxLength,
        bool $isNewRecord
    ): array {
        return $isNewRecord
            ? ['required', 'string', 'max:' . $maxLength]
            : ['sometimes', 'required', 'string', 'max:' . $maxLength];
    }
}