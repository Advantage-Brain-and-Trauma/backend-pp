<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class FormSubmissionPdfService
{
    /**
     * Generate a PDF for the given form submission, save it to
     * storage/app/public/form-pdfs/ and return the filename.
     *
     * Filename format: FirstName_LastName_YYYYMMDD_HHmmss.pdf
     * e.g.  Test_Testmh_20260505_070824.pdf
     *
     * @param  FormSubmission  $submission
     * @param  Form            $form
     * @param  User|null       $user
     * @return string  The saved filename (not the full path)
     */
    public function generate(FormSubmission $submission, Form $form, ?User $user): string
    {
        // ── 1. Build patient name for filename ──────────────────────────────
        $patientName = $this->resolvePatientName($submission, $user);
        $namePart    = $this->sanitizeForFilename($patientName);

        // ── 2. Build filename ────────────────────────────────────────────────
        $timestamp = $submission->created_at->format('Ymd_His');
        $filename  = "{$namePart}_{$timestamp}.pdf";

        // ── 3. Flatten the form schema into an ordered list of fields ────────
        $flatFields = $this->flattenFields($form->fields ?? []);

        // ── 4. Render HTML ───────────────────────────────────────────────────
        $html = $this->buildHtml($form, $flatFields, $submission->data ?? [], $patientName, $submission);

        // ── 5. Generate PDF via DomPDF ───────────────────────────────────────
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // ── 6. Save to storage ───────────────────────────────────────────────
        $directory = 'form-pdfs';
        Storage::disk('public')->makeDirectory($directory);
        Storage::disk('public')->put("{$directory}/{$filename}", $dompdf->output());

        return $filename;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Determine the patient's display name from submission data or user record.
     */
    private function resolvePatientName(FormSubmission $submission, ?User $user): string
    {
        // Try patient_name column first
        if (!empty($submission->patient_name)) {
            return $submission->patient_name;
        }

        // Try to find a "name" type field in the submitted data
        $data = $submission->data ?? [];
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['first'], $value['last'])) {
                $first = trim($value['first'] ?? '');
                $last  = trim($value['last']  ?? '');
                if ($first || $last) {
                    return trim("{$first} {$last}");
                }
            }
        }

        // Fall back to user name
        if ($user && !empty($user->name)) {
            return $user->name;
        }

        return 'Patient';
    }

    /**
     * Convert a name like "Test Testmh" → "Test_Testmh" (safe for filenames).
     */
    private function sanitizeForFilename(string $name): string
    {
        // Replace spaces with underscores, strip non-alphanumeric except underscore/hyphen
        $safe = preg_replace('/\s+/', '_', trim($name));
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '', $safe);
        return $safe ?: 'Patient';
    }

    /**
     * Walk the form schema (rows → cols → fields) and return a flat array
     * of field definitions in display order.
     */
    private function flattenFields(array $schema): array
    {
        $flat = [];

        // Schema can be {"rows": [...]} or a plain array of rows
        $rows = $schema['rows'] ?? (isset($schema[0]) ? $schema : []);

        foreach ($rows as $row) {
            foreach (($row['cols'] ?? []) as $col) {
                foreach (($col['fields'] ?? []) as $field) {
                    if (!empty($field['id']) && !empty($field['type'])) {
                        $flat[] = $field;
                    }
                }
            }
        }

        return $flat;
    }

    /**
     * Build the full HTML document that DomPDF will render.
     */
    private function buildHtml(
        Form $form,
        array $flatFields,
        array $data,
        string $patientName,
        FormSubmission $submission
    ): string {
        $formName  = htmlspecialchars($form->name ?? 'Form', ENT_QUOTES);
        $dateStr   = $submission->created_at->format('d M Y');
        $patientHtml = htmlspecialchars($patientName, ENT_QUOTES);

        $rowsHtml = '';
        foreach ($flatFields as $field) {
            $rowsHtml .= $this->renderFieldRow($field, $data);
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 13px;
    color: #111;
    padding: 32px 36px;
  }
  .header-line {
    border-top: 1px solid #aaa;
    margin-bottom: 18px;
  }
  h1 {
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 4px;
  }
  .subtitle {
    font-size: 12px;
    color: #444;
    margin-bottom: 16px;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 0;
  }
  td {
    border: 1px solid #bbb;
    padding: 8px 10px;
    vertical-align: top;
  }
  .label-cell {
    width: 38%;
    font-weight: bold;
    background: #fff;
  }
  .required-star {
    color: #dc2626;
  }
  .value-cell {
    width: 62%;
    background: #fff;
  }
  .full-cell {
    width: 100%;
    background: #fff;
    padding: 8px 10px;
  }
  .section-header {
    background: #f5f5f5;
    font-weight: bold;
    text-align: center;
    padding: 8px 10px;
    border: 1px solid #bbb;
  }
  .footer-line {
    border-bottom: 1px solid #aaa;
    margin-top: 18px;
  }
  .signature-img {
    max-width: 200px;
    max-height: 80px;
  }
  .file-link {
    color: #1d4ed8;
    word-break: break-all;
  }
</style>
</head>
<body>
  <div class="header-line"></div>
  <h1>{$formName}</h1>
  <div class="subtitle">{$dateStr} / {$patientHtml}</div>
  <table>
    {$rowsHtml}
  </table>
  <div class="footer-line"></div>
</body>
</html>
HTML;
    }

    /**
     * Render a single <tr> for a field, handling all field types.
     */
    private function renderFieldRow(array $field, array $data): string
    {
        $id       = $field['id'] ?? '';
        $type     = $field['type'] ?? 'text';
        $label    = htmlspecialchars($field['label'] ?? '', ENT_QUOTES);
        $required = !empty($field['required']);
        $star     = $required ? ' <span class="required-star">*</span>' : '';
        $rawValue = $data[$id] ?? null;

        // ── Paragraph / heading / line-break: full-width display ────────────
        if (in_array($type, ['paragraph', 'heading', 'linebreak', 'line_break'])) {
            $content = htmlspecialchars($field['content'] ?? $label, ENT_QUOTES);
            if ($type === 'linebreak' || $type === 'line_break') {
                $content = '<strong>Line break</strong>';
            }
            return "<tr><td class=\"full-cell\" colspan=\"2\">{$content}</td></tr>\n";
        }

        // ── Signature: render as image if base64, else show text ────────────
        if ($type === 'signature') {
            $valueHtml = '';
            if (!empty($rawValue) && str_starts_with($rawValue, 'data:image')) {
                $valueHtml = "<img class=\"signature-img\" src=\"{$rawValue}\" alt=\"Signature\">";
            } elseif (!empty($rawValue)) {
                $valueHtml = htmlspecialchars($rawValue, ENT_QUOTES);
            }
            return "<tr>
  <td class=\"label-cell\">{$label}{$star}</td>
  <td class=\"value-cell\">{$valueHtml}</td>
</tr>\n";
        }

        // ── File upload: show filename / path ────────────────────────────────
        if ($type === 'file') {
            $valueHtml = '';
            if (!empty($rawValue)) {
                $display   = htmlspecialchars(basename($rawValue), ENT_QUOTES);
                $valueHtml = "<span class=\"file-link\">{$display}</span>";
            }
            return "<tr>
  <td class=\"label-cell\">{$label}{$star}</td>
  <td class=\"value-cell\">{$valueHtml}</td>
</tr>\n";
        }

        // ── Address: render sub-fields ────────────────────────────────────────
        if ($type === 'address') {
            $parts = [];
            if (is_array($rawValue)) {
                foreach (['street', 'city', 'state', 'zip', 'country'] as $part) {
                    if (!empty($rawValue[$part])) {
                        $parts[] = htmlspecialchars($rawValue[$part], ENT_QUOTES);
                    }
                }
            }
            $valueHtml = implode(', ', $parts);
            return "<tr>
  <td class=\"label-cell\">{$label}{$star}</td>
  <td class=\"value-cell\">{$valueHtml}</td>
</tr>\n";
        }

        // ── Name: render sub-fields ───────────────────────────────────────────
        if ($type === 'name') {
            $parts = [];
            if (is_array($rawValue)) {
                foreach (['first', 'middle', 'last'] as $part) {
                    if (!empty($rawValue[$part])) {
                        $parts[] = htmlspecialchars($rawValue[$part], ENT_QUOTES);
                    }
                }
            } elseif (!empty($rawValue)) {
                $parts[] = htmlspecialchars($rawValue, ENT_QUOTES);
            }
            $valueHtml = implode(' ', $parts);
            return "<tr>
  <td class=\"label-cell\">{$label}{$star}</td>
  <td class=\"value-cell\">{$valueHtml}</td>
</tr>\n";
        }

        // ── Checkbox / multi-select: array of values ─────────────────────────
        if ($type === 'checkbox' && is_array($rawValue)) {
            $valueHtml = htmlspecialchars(implode(', ', $rawValue), ENT_QUOTES);
            return "<tr>
  <td class=\"label-cell\">{$label}{$star}</td>
  <td class=\"value-cell\">{$valueHtml}</td>
</tr>\n";
        }

        // ── Toggle: boolean display ───────────────────────────────────────────
        if ($type === 'toggle') {
            $display   = ($rawValue === '1' || $rawValue === true || $rawValue === 1) ? 'Yes' : 'No';
            $valueHtml = $display;
            return "<tr>
  <td class=\"label-cell\">{$label}{$star}</td>
  <td class=\"value-cell\">{$valueHtml}</td>
</tr>\n";
        }

        // ── Default: plain text value ─────────────────────────────────────────
        $valueHtml = '';
        if (is_array($rawValue)) {
            $valueHtml = htmlspecialchars(implode(', ', $rawValue), ENT_QUOTES);
        } elseif ($rawValue !== null) {
            $valueHtml = htmlspecialchars((string) $rawValue, ENT_QUOTES);
        }

        return "<tr>
  <td class=\"label-cell\">{$label}{$star}</td>
  <td class=\"value-cell\">{$valueHtml}</td>
</tr>\n";
    }
}
