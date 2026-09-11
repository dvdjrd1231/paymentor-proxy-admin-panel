<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A file sent with every email built from one notification template.
 *
 * Attached at send time by AdminOps::attachTemplateFiles(), which listens for
 * MessageSending — core's Mailable is vendored and has no attachments() to extend.
 */
class EmailTemplateAttachment extends Model
{
    protected $table = 'ext_email_template_attachments';

    protected $fillable = ['template_id', 'filename', 'path', 'filesize', 'mime_type'];

    /** Where the file lives, for the mailer and for deletion. */
    public function absolutePath(): string
    {
        return storage_path('app/' . $this->path);
    }
}
