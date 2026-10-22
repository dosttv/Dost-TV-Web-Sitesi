<?php

namespace App\Models;

use App\Support\ResponsiveImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InstagramVideo extends Model
{
    protected $fillable = [
        'instagram_media_id',
        'shortcode',
        'username',
        'speaker_name',
        'permalink',
        'embed_html',
        'caption',
        'thumbnail_url',
        'media_url',
        'cover_image',
        'media_type',
        'is_active',
        'sort_order',
        'posted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'posted_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(InstagramCategory::class, 'instagram_category_video')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * Resolves final public cover image URL using 3-tier priority:
     * 1. Manual uploaded cover image (cover_image)
     * 2. Graph API thumbnail_url
     * 3. Graph API media_url
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if (filled($this->cover_image)) {
            // Resized WebP variant; uploaded covers are often ~2 MB PNGs.
            return ResponsiveImage::url($this->cover_image, 640);
        }

        if (filled($this->thumbnail_url)) {
            return $this->thumbnail_url;
        }

        if (filled($this->media_url)) {
            return $this->media_url;
        }

        return null;
    }

    /**
     * Returns sanitized Instagram embed HTML ensuring only valid Instagram blockquotes are rendered.
     */
    public function getSafeEmbedHtmlAttribute(): ?string
    {
        if (blank($this->embed_html)) {
            return null;
        }

        if (! str_contains($this->embed_html, 'instagram-media') && ! str_contains($this->embed_html, 'instagram.com')) {
            return null;
        }

        $clean = preg_replace('#<script(?!.*platform\.instagram\.com/en_US/embeds\.js).*?>.*?</script>#is', '', $this->embed_html);

        return $clean;
    }
}
