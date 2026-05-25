<?php

namespace App\Http\Controllers;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OldFormController extends Controller
{
    public function index()
    {
        return view('forms.old-forms');
    }

    /**
     * GET /old-forms/list
     *
     * Return old forms from patient_portal.forms excluding already-synced ones.
     */
    public function list()
    {
        try {
            $allOldForms = DB::connection('patient_portal')->table('forms')->whereNull('deleted_at')->get();

            // Get slugs of currently active synced forms only.
            // Soft-deleted forms should be allowed to appear again for re-sync.
            $syncedSlugs = Form::query()->pluck('slug')->toArray();

            // Filter out old forms whose slug (title-slug + id) already exists in test_pp.forms
            $filteredForms = $allOldForms->filter(function ($form) use ($syncedSlugs) {
                $slug = Str::slug($form->title ?? 'untitled') . '-' . $form->id;
                return !in_array($slug, $syncedSlugs);
            })->values();

            return response()->json([
                'status'  => true,
                'message' => 'Forms retrieved successfully.',
                'data'    => $filteredForms,
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('admin_old_forms')->error('Error fetching old forms list', [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching forms.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /old-forms/{id}/sync
     *
     * Sync a single old form from patient_portal.forms into test_pp.forms
     * with JSON structure transformation.
     */
    public function sync($id)
    {
        try {
            $oldForm = DB::connection('patient_portal')->table('forms')->whereNull('deleted_at')->where('id', $id)->first();

            if (!$oldForm) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Old form not found.',
                ], 404);
            }

            $transformedFields = $this->transformJsonToFields($oldForm->json ?? null);
            $transformedFields = $this->decodeHtmlEntitiesRecursively($transformedFields);

            $slug = Str::slug($oldForm->title ?? 'untitled') . '-' . $oldForm->id;

            // Check if a soft-deleted form with this slug exists — restore it
            $existingForm = Form::withTrashed()->where('slug', $slug)->first();
            if ($existingForm) {
                if ($existingForm->trashed()) {
                    $existingForm->restore();
                    $existingForm->update([
                        'name'             => $oldForm->title ?? 'Untitled',
                        'description'      => $oldForm->description ?? null,
                        'email'            => $oldForm->email ?? null,
                        'fields'           => $transformedFields,
                        'is_active'        => 1,
                    ]);
                } else {
                    return response()->json([
                        'status'  => false,
                        'message' => 'This form has already been synced.',
                    ], 409);
                }
            } else {
                Form::create([
                    'name'             => $oldForm->title ?? 'Untitled',
                    'description'      => $oldForm->description ?? null,
                    'slug'             => $slug,
                    'email'            => $oldForm->email ?? null,
                    'fields'           => $transformedFields,
                    'created_by'       => auth()->id(),
                    'is_active'        => 1,
                    'submission_count' => 0,
                ]);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Form synced successfully.',
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('admin_old_forms')->error('Error syncing old form', [
                'old_form_id' => $id,
                'error'       => $e->getMessage(),
                'line'        => $e->getLine(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while syncing the form.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transform old form JSON (flat array format) into the new rows/cols/fields structure.
     *
     * Old format: [[{type, label, required, values, ...}, ...]]
     * New format: {"rows":[{"id":"r1","cols":[{"fields":[{id, type, label, ...}]}]}]}
     */
    private function transformJsonToFields($json)
    {
        if (empty($json)) {
            return ['rows' => []];
        }

        $oldFields = is_string($json) ? json_decode($json, true) : (array) $json;

        // The old format is a nested array [[...fields...]], flatten the first level
        if (isset($oldFields[0]) && is_array($oldFields[0]) && isset($oldFields[0][0])) {
            $oldFields = $oldFields[0];
        }

        $rows = [];
        $fieldIndex = 1;

        foreach ($oldFields as $oldField) {
            if (!is_array($oldField)) {
                continue;
            }

            $type = $this->mapFieldType($oldField['type'] ?? 'text');
            $label = $oldField['label'] ?? '';
            $required = isset($oldField['required']) ? (bool) $oldField['required'] : false;

            // Map options from old values array
            $options = [];
            if (!empty($oldField['values']) && is_array($oldField['values'])) {
                foreach ($oldField['values'] as $val) {
                    if (is_array($val) && isset($val['label'])) {
                        $options[] = $val['label'];
                    } elseif (is_string($val)) {
                        $options[] = $val;
                    }
                }
            }

            // Determine content for header/paragraph types
            $content = '';
            if (in_array($type, ['header', 'paragraph'])) {
                $content = $label;
            }

            // Determine placeholder
            $placeholder = '';
            if (!in_array($type, ['header', 'paragraph', 'divider', 'radio', 'checkbox', 'toggle', 'rating', 'scale', 'signature', 'file', 'image', 'submit'])) {
                $placeholder = 'Enter ' . strtolower($label) . '...';
            }

            $newField = [
                'id'          => 'f' . $fieldIndex,
                'type'        => $type,
                'label'       => $label,
                'placeholder' => $placeholder,
                'required'    => $required,
                'helpText'    => '',
                'width'       => '100%',
                'cssClass'    => '',
                'options'     => $options,
                'content'     => $content,
                'buttonText'  => $type === 'submit' ? 'Submit Form' : '',
                'style'       => [
                    'bold'     => false,
                    'italic'   => false,
                    'fontSize' => '13',
                    'color'    => '',
                    'bgColor'  => '',
                ],
            ];

            $rows[] = [
                'id'   => 'r' . $fieldIndex,
                'cols' => [
                    ['fields' => [$newField]],
                ],
            ];

            $fieldIndex++;
        }

        return ['rows' => $rows];
    }

    /**
     * Decode HTML entities from legacy form text before storing new schema JSON.
     */
    private function decodeHtmlEntities($value): string
    {
        if (!is_string($value) || $value === '') {
            return (string) $value;
        }

        $decoded = $value;
        $maxPasses = 5;

        // Decode repeatedly to handle double-encoded strings like "&amp;amp;" -> "&".
        for ($i = 0; $i < $maxPasses; $i++) {
            $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        return str_replace("\u{00A0}", ' ', $decoded);
    }

    /**
     * Recursively decode entities for all strings in the schema payload.
     */
    private function decodeHtmlEntitiesRecursively($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->decodeHtmlEntitiesRecursively($item);
            }

            return $value;
        }

        if (is_string($value)) {
            return $this->decodeHtmlEntities($value);
        }

        return $value;
    }

    /**
     * Map old form field type to new platform field type.
     */
    private function mapFieldType(string $oldType): string
    {
        $typeMap = [
            'text'           => 'text',
            'textarea'       => 'textarea',
            'number'         => 'number',
            'email'          => 'email',
            'phone'          => 'phone',
            'date'           => 'date',
            'time'           => 'time',
            'password'       => 'password',
            'file'           => 'file',
            'select'         => 'dropdown',
            'radio-group'    => 'radio',
            'checkbox-group' => 'checkbox',
            'header'         => 'header',
            'paragraph'      => 'paragraph',
            'hidden'         => 'text',
            'button'         => 'submit',
            'autocomplete'   => 'dropdown',
            'starRating'     => 'rating',
        ];

        return $typeMap[$oldType] ?? 'text';
    }
}

