<?php

namespace VitaminD\Plugins\Archive\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use VitaminD\Plugins\Archive\Http\Controllers\Archive\FileController;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('archive-plugin.max_upload_size'),
                'extensions:'.implode(',', config('archive-plugin.allowed_extensions')),
            ],
            'folder_id' => ['nullable', 'uuid', 'exists:folders,uuid'],
            'visibility' => ['nullable', Rule::in(FileController::VISIBILITIES)],
        ];
    }
}
