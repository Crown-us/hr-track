<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDay extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'scheduled_start' => 'immutable_datetime', 'scheduled_end' => 'immutable_datetime', 'clock_in' => 'immutable_datetime', 'clock_out' => 'immutable_datetime', 'voided_at' => 'immutable_datetime', 'version' => 'integer', 'tolerance_minutes' => 'integer'];
    }

    public function forDisplay(): array
    {
        $result = $this->only('id', 'version', 'tolerance_minutes');
        foreach (['scheduled_start', 'scheduled_end', 'clock_in', 'clock_out'] as $field) {
            $result[$field] = $this->$field?->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i');
        }

        $sources = [];
        foreach (['clock_in', 'clock_out'] as $field) {
            $result[$field.'_source'] = $this->$field ? ($this->{$field.'_source'} ?? 'hr') : null;
            if ($result[$field.'_source']) {
                $sources[] = $result[$field.'_source'];
            }
        }
        $sources = array_unique($sources);

        return [...$result, 'voided' => $this->voided_at !== null, 'source' => count($sources) > 1 ? 'Web pegawai / koreksi HR' : (in_array('web', $sources, true) ? 'Web pegawai' : 'Dicatat HR')];
    }
}
