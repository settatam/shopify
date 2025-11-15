<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedBatch extends Model
{
    //
    protected $fillable = ['channel_id','feed_type','marketplace_ids','content_type','feed_document_id','feed_id','status','submitted_count','processed_count','result_feed_document_id','error'];
    protected $casts = ['marketplace_ids' => 'array'];
    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
    public function items(): HasMany { return $this->hasMany(FeedBatchItem::class); }
}
