<?php

namespace App\Events;

use App\Models\Record;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecordSaved
{
    use Dispatchable, SerializesModels;

    public Record $record;
    public string $trigger;

    public function __construct(Record $record, string $trigger)
    {
        $this->record = $record;
        $this->trigger = $trigger;
    }
}
