<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileUpload extends Model
{
    protected $fillable = [
        'file_name',
        'merged_file_name',
        'destination_path',
        'user_id',
        'company_id',
        'supplier_id',
        'status',
        'source_files',
        'import_hash',
        'source_hashes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'source_files' => 'array',
        'source_hashes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->destination_path . '/' . $this->file_name);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * W6.I "Importer contract" item 3 (w6-brief.md) -- sha256 of the uploaded file's raw content,
     * replacing the filename-only `(supplier_id, company_id, file_name)` dedupe. Pure function,
     * kept here (not inlined at each call site) so every uploader path hashes content the exact
     * same way.
     *
     * W6.I residual fix round: neither this file nor TaskController.php declares
     * strict_types=1, so a caller that (incorrectly) feeds an unguarded
     * `file_get_contents()` result straight in here would have `bool(false)` silently
     * coerced to `''` by PHP's weak scalar typing, and `hash('sha256', '')` returns a
     * normal-looking digest instead of surfacing the read failure. Rejecting an empty
     * string turns that silent-false-becomes-a-real-hash failure mode into a thrown
     * exception every call site's existing try/catch already has to handle for other
     * reasons -- never let a read failure (or a genuinely empty file) produce a hash
     * that could collide with, or be indistinguishable from, another file's content.
     */
    public static function hashContent(string $content): string
    {
        if ($content === '') {
            throw new \InvalidArgumentException('FileUpload::hashContent() cannot hash empty content.');
        }

        return hash('sha256', $content);
    }

    /**
     * W6.I residual fix round -- reads a file from disk and hashes its content, returning
     * null (never throwing) when the read fails (`file_get_contents()` returns `false`),
     * when the path is otherwise unreadable, or when the file is empty. This is the one
     * safe way to go from a filesystem path to an `import_hash`/`source_hashes` entry:
     * every call site that used to do `hashContent(file_get_contents($path))` inline --
     * and so silently hashed an empty string on a read failure -- must use this instead.
     */
    public static function hashFile(string $path): ?string
    {
        $bytes = @file_get_contents($path);

        if ($bytes === false || $bytes === null) {
            return null;
        }

        try {
            return static::hashContent($bytes);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }
}
