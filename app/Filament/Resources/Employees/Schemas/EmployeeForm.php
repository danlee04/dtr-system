<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Headings, not cards: a card inside the modal is a box in a box. Each heading is marked
                // in amber and a rule parts the sections (dtr-form-section, in public/css/dtr.css).
                // Who they are first: the photo beside the name, so the photo leaves no empty row.
                Section::make('Name')
                    ->contained(false)
                    ->extraAttributes(['class' => 'dtr-form-section'])
                    ->columns(['default' => 1, 'md' => 4])
                    ->columnSpanFull()
                    ->schema([
                        // Here a rounded square with a camera; the Profile page keeps the round one.
                        self::photoField()
                            ->panelLayout('compact')
                            ->placeholder(svg('heroicon-o-camera')->toHtml().'Drag &amp; Drop your files or <span class="filepond--label-action">Browse</span>')
                            ->extraAttributes(['class' => 'dtr-photo-drop']),
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->columnSpan(['default' => 1, 'md' => 3])
                            ->extraAttributes(['class' => 'dtr-form-names'])
                            ->schema([
                                TextInput::make('last_name')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('first_name')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('middle_name')
                                    ->maxLength(100),
                                TextInput::make('suffix')
                                    ->maxLength(10),
                            ]),
                    ]),
                // Four across on a laptop, the same as the employee's page; two on a tablet.
                Section::make('Employment')
                    ->contained(false)
                    ->extraAttributes(['class' => 'dtr-form-section dtr-form-section-divided'])
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('employee_number')
                            ->required()
                            ->maxLength(30)
                            ->unique(),
                        Select::make('office_id')
                            ->label('Office')
                            ->relationship('office', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('employment_status')
                            ->options(EmploymentStatus::class)
                            ->required(),
                        TextInput::make('position')
                            ->helperText('As on the leave form, e.g. Nurse II.')
                            ->maxLength(100),
                    ]),
                Section::make('Attendance')
                    ->contained(false)
                    ->extraAttributes(['class' => 'dtr-form-section dtr-form-section-divided'])
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('biometric_id')
                            ->label('Biometric ID')
                            ->helperText('Its ID on the device. Blank until known.')
                            ->maxLength(20)
                            ->alphaNum()
                            ->unique(),
                        DatePicker::make('date_hired'),
                        DatePicker::make('date_separated')
                            ->afterOrEqual('date_hired'),
                        // The fourth column: the two switches stacked, label beside each, so they read as settings.
                        Grid::make(1)
                            ->extraAttributes(['class' => 'dtr-form-switches'])
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                // HR alone knows whose flexitime request was granted (HR, 2026-11-19).
                                Toggle::make('flexitime')
                                    ->label('Flexitime'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Their photo, here for HR and on the Profile page for the employee:
     * cropped square, kept on the private disk. Not SVG, which can carry script.
     */
    public static function photoField(): FileUpload
    {
        return FileUpload::make('photo_path')
            ->label('Photo')
            ->avatar()
            ->imageEditor()
            ->circleCropper()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(10240)
            ->disk(Employee::PHOTO_DISK)
            ->directory(Employee::PHOTO_DIRECTORY)
            ->visibility('private')
            ->helperText('A JPG, PNG or WebP of up to 10 MB, cropped square.');
    }
}
