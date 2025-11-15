<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'event_type',
        'name',
        'subject',
        'body_html',
        'body_text',
        'available_variables',
        'from_name',
        'from_email',
        'reply_to',
        'is_active',
        'is_system',
        'category',
        'settings',
    ];

    protected $casts = [
        'available_variables' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /**
     * Get the shop that owns the template.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get notification logs for this template.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Render the subject with variables.
     */
    public function renderSubject(array $variables): string
    {
        return $this->replaceVariables($this->subject, $variables);
    }

    /**
     * Render the HTML body with variables.
     */
    public function renderBodyHtml(array $variables): string
    {
        return $this->replaceVariables($this->body_html, $variables);
    }

    /**
     * Render the plain text body with variables.
     */
    public function renderBodyText(array $variables): string
    {
        $text = $this->body_text ?? strip_tags($this->body_html);
        return $this->replaceVariables($text, $variables);
    }

    /**
     * Replace template variables with actual values.
     */
    protected function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            // Handle both {variable} and {{variable}} syntax
            $content = str_replace(['{' . $key . '}', '{{' . $key . '}}'], $value, $content);
        }

        return $content;
    }

    /**
     * Validate that all required variables are provided.
     */
    public function validateVariables(array $variables): bool
    {
        $required = $this->extractRequiredVariables();

        foreach ($required as $variable) {
            if (!isset($variables[$variable])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract required variables from template content.
     */
    protected function extractRequiredVariables(): array
    {
        $variables = [];
        $content = $this->subject . ' ' . $this->body_html;

        // Match {variable} and {{variable}} patterns
        preg_match_all('/\{(\w+)\}/', $content, $matches);

        if (!empty($matches[1])) {
            $variables = array_unique($matches[1]);
        }

        return $variables;
    }

    /**
     * Clone template for customization.
     */
    public function duplicate(string $name): self
    {
        $new = $this->replicate();
        $new->name = $name;
        $new->is_system = false;
        $new->save();

        return $new;
    }

    /**
     * Scope: Active templates only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by event type.
     */
    public function scopeForEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope: Filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
