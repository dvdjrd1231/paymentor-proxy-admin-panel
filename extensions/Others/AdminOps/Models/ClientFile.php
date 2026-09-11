<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/** A file an admin keeps against one client, for the Client Profile's Files panel. */
class ClientFile extends Model
{
    protected $table = 'ext_client_files';

    protected $fillable = ['user_id', 'uploaded_by', 'filename', 'path', 'filesize', 'mime_type'];

    /** Where the file lives, for download and for deletion. */
    public function absolutePath(): string
    {
        return storage_path('app/' . $this->path);
    }

    /** The size as the panel shows it. */
    public function readableSize(): string
    {
        $bytes = (int) $this->filesize;

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, $unit === 'B' ? 0 : 1) . ' ' . $unit;
            }

            $bytes /= 1024;
        }

        return $bytes . ' B';
    }
}
