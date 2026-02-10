<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Http\Requests\DocumentProcessingRequest;
use Illuminate\Support\Facades\Validator;

class FileValidationTest extends TestCase
{
    public function test_validates_required_fields()
    {
        $data = [];

        $request = new DocumentProcessingRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('document_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('supplier_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('company_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('document_type', $validator->errors()->toArray());
        $this->assertArrayHasKey('file_path', $validator->errors()->toArray());
    }

    public function test_validates_document_id_uuid_format()
    {
        $data = [
            'document_id' => 'not-a-uuid',
            'supplier_id' => 'test-supplier',
            'company_id' => 1,
            'document_type' => 'PDF',
            'file_path' => 's3://bucket/file.pdf',
        ];

        $request = new DocumentProcessingRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('document_id', $validator->errors()->toArray());
    }

    public function test_validates_document_type_enum()
    {
        $data = [
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'supplier_id' => 'test-supplier',
            'company_id' => 1,
            'document_type' => 'INVALID',
            'file_path' => 's3://bucket/file.pdf',
        ];

        $request = new DocumentProcessingRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('document_type', $validator->errors()->toArray());
    }

    public function test_rejects_directory_traversal_in_file_path()
    {
        $data = [
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'supplier_id' => 'test-supplier',
            'company_id' => 1,
            'document_type' => 'PDF',
            'file_path' => '../../../etc/passwd',
        ];

        $request = new DocumentProcessingRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('file_path', $validator->errors()->toArray());
    }

    public function test_accepts_valid_s3_file_path()
    {
        $data = [
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'supplier_id' => 'test-supplier',
            'company_id' => 1,
            'document_type' => 'PDF',
            'file_path' => 's3://my-bucket/company/supplier/documents/file.pdf',
        ];

        $request = new DocumentProcessingRequest();
        $validator = Validator::make($data, $request->rules());

        // May fail on company_id foreign key, but file_path should be valid
        $errors = $validator->errors()->toArray();
        $this->assertArrayNotHasKey('file_path', $errors);
    }

    public function test_accepts_all_valid_document_types()
    {
        $validTypes = ['AIR', 'PDF', 'image', 'email'];

        foreach ($validTypes as $type) {
            $data = [
                'document_id' => '550e8400-e29b-41d4-a716-446655440000',
                'supplier_id' => 'test-supplier',
                'company_id' => 1,
                'document_type' => $type,
                'file_path' => 's3://bucket/file.pdf',
            ];

            $request = new DocumentProcessingRequest();
            $validator = Validator::make($data, $request->rules());

            $errors = $validator->errors()->toArray();
            $this->assertArrayNotHasKey('document_type', $errors, "Document type {$type} should be valid");
        }
    }

    public function test_validates_metadata_is_array()
    {
        $data = [
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'supplier_id' => 'test-supplier',
            'company_id' => 1,
            'document_type' => 'PDF',
            'file_path' => 's3://bucket/file.pdf',
            'metadata' => 'not-an-array',
        ];

        $request = new DocumentProcessingRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('metadata', $validator->errors()->toArray());
    }

    public function test_validates_callback_url_format()
    {
        $data = [
            'document_id' => '550e8400-e29b-41d4-a716-446655440000',
            'supplier_id' => 'test-supplier',
            'company_id' => 1,
            'document_type' => 'PDF',
            'file_path' => 's3://bucket/file.pdf',
            'callback_url' => 'not-a-valid-url',
        ];

        $request = new DocumentProcessingRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('callback_url', $validator->errors()->toArray());
    }
}
