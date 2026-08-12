<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TechnicalSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'es_semielaborado' => ['required', 'boolean'],
            'packaging_id' => ['required_if:es_semielaborado,false', 'nullable', 'exists:inventory_packagings,id'],
            'material_id' => ['required_if:es_semielaborado,true', 'nullable', 'exists:inventory_materials,id'],
            'fecha_vigencia_desde' => ['required', 'date'],
            'fecha_vigencia_hasta' => ['nullable', 'date', 'after_or_equal:fecha_vigencia_desde'],
            'activo' => ['required', 'boolean'],
            'observacion' => ['nullable', 'string'],

            'unit_items' => ['array'],
            'unit_items.*.material_id' => ['nullable', 'exists:inventory_materials,id'],
            'unit_items.*.replacement_material_id' => ['nullable', 'exists:inventory_materials,id'],
            'unit_items.*.cantidad_estandar' => ['nullable', 'numeric', 'gt:0'],
            'unit_items.*.calibre' => ['nullable', 'string', 'max:20'],
            'pallet_items' => ['array'],
            'pallet_items.*.material_id' => ['nullable', 'exists:inventory_materials,id'],
            'pallet_items.*.replacement_material_id' => ['nullable', 'exists:inventory_materials,id'],
            'pallet_items.*.cantidad_estandar' => ['nullable', 'numeric', 'gt:0'],
            'pallet_items.*.calibre' => ['nullable', 'string', 'max:20'],

            'packaging_spec' => [Rule::requiredIf(! $this->boolean('es_semielaborado')), 'nullable', 'array'],
            'packaging_spec.identificacion' => ['nullable', 'array'],
            'packaging_spec.identificacion.*' => ['nullable', 'string', 'max:500'],
            'packaging_spec.formato' => ['nullable', 'array'],
            'packaging_spec.formato.*' => ['nullable', 'string', 'max:100'],
            'packaging_spec.calidad' => ['nullable', 'array'],
            'packaging_spec.calidad.*' => ['nullable', 'string', 'max:1000'],
            'packaging_spec.tolerancias' => ['nullable', 'array'],
            'packaging_spec.tolerancias.*' => ['nullable', 'string', 'max:3000'],
            'packaging_spec.paletizaje' => ['nullable', 'array'],
            'packaging_spec.paletizaje.*' => ['nullable', 'string', 'max:3000'],
            'packaging_spec.responsables' => ['nullable', 'array'],
            'packaging_spec.responsables.*' => ['nullable', 'string', 'max:500'],

            'existing_images' => ['array', 'max:20'],
            'existing_images.*.id' => ['required', 'integer', 'exists:inventory_technical_sheet_images,id'],
            'existing_images.*.descripcion' => ['required', 'string', 'max:3000'],
            'existing_images.*.orden' => ['nullable', 'integer', 'min:0'],
            'new_images' => ['array', 'max:20'],
            'new_images.*.file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'new_images.*.descripcion' => ['required', 'string', 'max:3000'],
            'new_images.*.orden' => ['nullable', 'integer', 'min:0'],
            'removed_image_ids' => ['array'],
            'removed_image_ids.*' => ['integer', 'exists:inventory_technical_sheet_images,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la ficha es obligatorio.',
            'new_images.*.file.required' => 'Cada imagen debe incluir un archivo.',
            'new_images.*.file.image' => 'El archivo debe ser una imagen válida.',
            'new_images.*.file.mimes' => 'Las imágenes deben ser JPG, PNG o WebP.',
            'new_images.*.file.max' => 'Cada imagen puede pesar como máximo 8 MB.',
            'new_images.*.descripcion.required' => 'Cada imagen debe tener una descripción.',
            'existing_images.*.descripcion.required' => 'Cada imagen debe mantener una descripción.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $existingCount = count($this->input('existing_images', []));
                $newCount = count($this->file('new_images', []));

                if (($existingCount + $newCount) > 20) {
                    $validator->errors()->add('new_images', 'La ficha puede tener como máximo 20 imágenes.');
                }
            },
        ];
    }
}
