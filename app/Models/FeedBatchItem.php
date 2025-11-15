<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedBatchItem extends Model
{
    //
    protected $fillable = ['feed_batch_id','sku','operation','payload_json','result_code','result_message','result_json'];
    protected $casts = ['payload_json' => 'array','result_json' => 'array'];
    public function batch(): BelongsTo { return $this->belongsTo(FeedBatch::class, 'feed_batch_id'); }
}
