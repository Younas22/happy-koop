<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'disk', 'path', 'type', 'mime_type', 'size_bytes', 'width', 'height',
        'alt', 'original_filename', 'folder', 'thumbnail_path', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return $this->publicUrl($this->path);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? $this->publicUrl($this->thumbnail_path) : null;
    }

    private function publicUrl(string $path): string
    {
        return Storage::disk($this->disk ?: 'public')->url($path);
    }
}
