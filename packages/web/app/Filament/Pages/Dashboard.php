<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        $connections = array_keys(array_filter(config('timeseries.connections'), fn ($c) => isset($c['driver']) && $c['driver'] !== 'aggregate'));

        return $schema->components([
            Select::make('connection')
                ->label('Connection')
                ->options(array_combine($connections, $connections))
                ->default(config('timeseries.default'))
                ->live(),
        ]);
    }
}
