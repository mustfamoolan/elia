<?php

namespace App\Support;

use App\Models\HomeworkFile;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class HomeworkStorage
{
    /**
     * الحد الأقصى لحجم الملف: 10 ميغابايت.
     */
    public const MAX_KB = 10240;

    /**
     * أنواع الملفات المسموحة: PDF، صور، Word.
     *
     * @return array<int, mixed>
     */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:'.self::MAX_KB,
            File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx']),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function multipleRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'array',
            'max:5',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function fileRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:'.self::MAX_KB,
            File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx']),
        ];
    }

    public static function store(
        UploadedFile $file,
        ?HomeworkTask $task,
        ?HomeworkSubmission $submission,
        ?User $uploader,
    ): HomeworkFile {
        $directory = 'homework/'.($task?->id ?? 'general');

        if ($submission) {
            $directory .= '/submission-'.$submission->id;
        }

        $path = $file->store($directory, 'local');

        return HomeworkFile::create([
            'homework_task_id' => $task?->id,
            'homework_submission_id' => $submission?->id,
            'uploaded_by' => $uploader?->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public static function delete(HomeworkFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);

        $file->delete();
    }
}
