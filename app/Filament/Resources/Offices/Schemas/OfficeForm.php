<?php

namespace App\Filament\Resources\Offices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Three across, so a short form fills the modal instead of leaving half of it blank.
            ->columns(3)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                TextInput::make('head_name')
                    ->label('Head of office')
                    ->helperText('Printed under "In Charge" on Form 48.')
                    ->maxLength(255),
                // Label on top, so the switch lines up with the fields beside it.
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false),
            ]);
    }
}
