<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Enums\PriorityEnum;
use App\Filament\App\Resources\TaskResource\Pages;
use App\Filament\App\Resources\WorkSessionResource\Pages\CreateWorkSession;
use App\Helpers\PejotaHelper;
use App\Models\Status;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\SpatieTagsEntry;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Parallax\FilamentComments\Infolists\Components\CommentsEntry;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = MenuSortEnum::TASKS->value;

    public static function getModelLabel(): string
    {
        return __('Task');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::DAILY_WORK->value);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with('client')
            ->opened();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $result = [
            'Status' => $record->status->name,
        ];

        if ($record->client) {
            $result['Client'] = $record->client->labelName;
        }

        return $result;
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('edit')
                ->hiddenLabel()
                ->url(Pages\EditTask::getUrl([$record->id]))
                ->icon('heroicon-o-pencil')
                ->size(ActionSize::ExtraSmall)
                ->tooltip(__('Edit Task')),

            Action::make('session')
                ->hiddenLabel()
                ->url(CreateWorkSession::getUrl([
                    'task' => $record->id,
                ]))
                ->icon('heroicon-o-play')
                ->color(Color::Amber)
                ->size(ActionSize::ExtraSmall)
                ->tooltip(__('Start a session for task')),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return Pages\ViewTask::getUrl([$record->id]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('client')
                        ->translateLabel()
                        ->relationship('client', 'name')
                        ->preload()->searchable(),
                    Forms\Components\Select::make('project_id')
                        ->label('Project')
                        ->translateLabel()
                        ->relationship(
                            'project',
                            'name',
                            fn (Builder $query, Forms\Get $get) => $query->byClient($get('client'))->orderBy('name')
                        )
                        ->searchable()->preload(),
                    Forms\Components\Select::make('parent_task')
                        ->translateLabel()
                        ->relationship('parent', 'title')
                        ->searchable(),
                ]),
                Forms\Components\TextInput::make('title')
                    ->translateLabel()
                    ->columnSpanFull()
                    ->required(),

                Forms\Components\Section::make(__('Details'))
                    ->collapsible()
                    ->compact()
                    ->schema([
                        Forms\Components\SpatieTagsInput::make('tags'),

                        Forms\Components\RichEditor::make('description')
                            ->translateLabel()
                            ->columnSpanFull()
                            ->extraInputAttributes(
                                ['style' => 'max-height: 300px; overflow: scroll'])
                            ->fileAttachmentsDisk('tasks')
                            ->fileAttachmentsDirectory(auth()->user()->company->id)
                            ->fileAttachmentsVisibility('private'),

                    ]),

                Forms\Components\Section::make('checklist')
                    ->label('Checklist')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TableRepeater::make('checklist')
                            ->hiddenLabel()
                            ->addActionLabel(__('Add item'))
                            ->cloneable()
                            ->schema([
                                Forms\Components\TextInput::make('item')
                                    ->required(),
                                Forms\Components\Checkbox::make('completed')
                                    ->translateLabel(),
                            ])
                            ->defaultItems(0)
                            ->colStyles([
                                'item' => 'width:90%',
                            ]),

                    ]),

                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\Select::make('priority')
                        ->translateLabel()
                        ->options(PriorityEnum::class)
                        ->default(PriorityEnum::MEDIUM)
                        ->required(),
                    Forms\Components\Select::make('status_id')
                        ->label('Status')
                        ->translateLabel()
                        ->required()
                        ->options(
                            Status::orderBy('sort_order')->pluck('name', 'id')
                        )
                        ->default(Status::orderBy('sort_order')->first()->id),

                    Forms\Components\TextInput::make('effort')
                        ->translateLabel()
                        ->numeric(),
                    Forms\Components\Select::make('effort_unit')
                        ->translateLabel()
                        ->options([
                            'h' => 'Hours',
                            'm' => 'Minutes',
                        ])
                        ->default('h'),

                ]),

                Forms\Components\Grid::make(5)->schema([
                    Forms\Components\DatePicker::make('due_date')
                        ->translateLabel(),

                    Forms\Components\DatePicker::make('planned_start')
                        ->translateLabel(),
                    Forms\Components\DatePicker::make('planned_end')
                        ->translateLabel(),
                    Forms\Components\DatePicker::make('actual_start')
                        ->translateLabel(),
                    Forms\Components\DatePicker::make('actual_end')
                        ->translateLabel(),

                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                'client.name',
                'project.name',
                'due_date',
                'status.name',
            ])
            ->columns([
                Tables\Columns\IconColumn::make('priority')
                    ->label('')
                    ->sortable()
                    ->icon(fn ($state) => PriorityEnum::from($state)->getIcon())
                    ->color(fn ($state) => PriorityEnum::from($state)->getColor())
                    ->tooltip(fn ($state) => PriorityEnum::from($state)->getLabel())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('title')
                    ->translateLabel()
                    ->wrap()
                    ->searchable(),
                Tables\Columns\SelectColumn::make('status_id')
                    ->label('Status')
                    ->options(fn (): array => Status::all()->pluck('name', 'id')->toArray())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\ColorColumn::make('status.color')
                    ->label('')
                    ->tooltip(fn (Model $record) => $record->status->name)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('due_date')
                    ->translateLabel()
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('effort')
                    ->translateLabel()
                    ->formatStateUsing(fn (Model $record): string => $record->effort.' '.$record->effort_unit)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('planned_start')
                    ->translateLabel()
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('planned_end')
                    ->translateLabel()
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('client.labelName')
                    ->translateLabel()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->translateLabel()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\SpatieTagsColumn::make('tags')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name')
                    ->multiple(true)
                    ->preload(),
                Tables\Filters\SelectFilter::make('client')
                    ->translateLabel()
                    ->relationship('client', 'name'),
                Tables\Filters\Filter::make('due_date_not_empty')
                    ->form([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\ToggleButtons::make('due_date')
                                ->translateLabel()
                                ->options([
                                    'not_empty' => __('Has due date'),
                                    'empty' => __('No due date'),
                                ]),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_date'] == 'not_empty',
                                fn (Builder $query): Builder => $query->whereNotNull('due_date')
                            )
                            ->when(
                                $data['due_date'] == 'empty',
                                fn (Builder $query): Builder => $query->whereNull('due_date')
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['due_date']) {
                            return $data['due_date'] == 'not_empty' ? __('Has due date') : __('No due date');
                        }

                        return null;
                    }),
                Tables\Filters\Filter::make('due_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->translateLabel(),
                        Forms\Components\DatePicker::make('to')
                            ->translateLabel(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->where('due_date', '>=', $data['from'])
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->where('due_date', '<=', $data['to'])
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['from'] || $data['to']) {
                            return __('Due date').': '.$data['from'].' - '.$data['to'];
                        }

                        return null;
                    }),
            ], layout: Tables\Enums\FiltersLayout::Modal)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    CommentsAction::make(),
                    Tables\Actions\EditAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->persistColumnSearchesInSession();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Split::make([
                    Grid::make(1)->schema([
                        Section::make([
                            SpatieTagsEntry::make('tags')
                                ->label('')
                                ->icon('heroicon-o-tag'),

                            Grid::make(2)->schema([
                                TextEntry::make('project.name')
                                    ->label('')
                                    ->icon('heroicon-o-presentation-chart-bar'),

                                TextEntry::make('client.name')
                                    ->label('')
                                    ->icon('heroicon-o-building-office'),
                            ]),

                            TextEntry::make('description')
                                ->translateLabel()
                                ->formatStateUsing(fn (string $state): HtmlString => new HtmlString($state))
                                ->icon('heroicon-o-document-text'),

                            Grid::make(4)->schema([
                                TextEntry::make('planned_start')
                                    ->translateLabel()
                                    ->date()
                                    ->icon('heroicon-o-calendar'),
                                TextEntry::make('planned_end')
                                    ->translateLabel()
                                    ->date()
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('actual_start')
                                    ->translateLabel()
                                    ->date()
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('actual_end')
                                    ->translateLabel()
                                    ->date()
                                    ->icon('heroicon-o-calendar'),
                            ]),
                        ]),

                        Tabs::make('Tabs')->schema([
                            Tabs\Tab::make('Checklist')
                                ->badge(fn (Model $record): int => $record->checklist ? count($record->checklist) : 0)
                                ->schema([
                                    RepeatableEntry::make('checklist')
                                        ->contained(false)
                                        ->hiddenLabel()
                                        ->schema([
                                            TextEntry::make('item')
                                                ->hiddenLabel()
                                                ->formatStateUsing(function ($state, $component): HtmlString {
                                                    if (self::getStateCompleted($component)) {
                                                        $state = '<s>'.$state.'</s>';
                                                    }

                                                    return new HtmlString($state);
                                                })
                                                ->prefixAction(fn ($component) => Action::make('checkCompleted')
                                                    ->icon(self::getStateCompleted($component) ? 'heroicon-o-check' : 'heroicon-o-stop')
                                                    ->color(self::getStateCompleted($component) ? Color::Green : Color::Gray)
                                                    ->action(function (Model $record, $component) {
                                                        $index = explode('.', $component->getStatePath())[1];
                                                        $checklist = $record->checklist;
                                                        $checklist[$index]['completed'] = ! $record->checklist[$index]['completed'];
                                                        $record->checklist = $checklist;
                                                        $record->save();
                                                    })
                                                ),
                                        ]),
                                ]),

                            Tabs\Tab::make('Subtasks')
                                ->translateLabel()
                                ->badge(fn (Model $record): int => $record->children->count())
                                ->schema([
                                    RepeatableEntry::make('children')
                                        ->hiddenLabel()
                                        ->contained(false)
                                        ->getStateUsing(function (Model $record) {
                                            $items = $record->children;
                                            foreach ($items as $key => $value) {
                                                $value->sort = $key;
                                            }

                                            return $items;
                                        })
                                        ->schema([
                                            Grid::make(12)->schema([
                                                IconEntry::make('priority')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->icon(fn ($state) => PriorityEnum::from($state)->getIcon())
                                                    ->color(fn ($state) => PriorityEnum::from($state)->getColor())
                                                    ->tooltip(fn ($state) => PriorityEnum::from($state)->getLabel()),

                                                TextEntry::make('status.name')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->badge()
                                                    ->color(fn (Model $record): array => Color::hex($record->status->color)),
                                                TextEntry::make('title')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->columnSpan(8)
                                                    ->action(
                                                        Action::make('view')
                                                            ->infolist(fn (Model $record) => self::infolist(
                                                                (new Infolist)->record($record)
                                                            ))
                                                            ->modalWidth(MaxWidth::FitContent)
                                                            ->modalCancelAction(false)
                                                            ->modalSubmitActionLabel('Close')
                                                    ),
                                                TextEntry::make('due_date')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->date()
                                                    ->icon('heroicon-o-calendar')
                                                    ->columnSpan(2),
                                            ]),
                                        ]),
                                ]),
                        ]),

                        Tabs::make('Tabs')->schema([
                            Tabs\Tab::make('Comments')
                                ->translateLabel()
                                ->badge(fn (Model $record): int => $record->filamentComments->count())
                                ->schema([
                                    CommentsEntry::make('filament_comments')
                                        ->columnSpanFull(),
                                ]),

                            Tabs\Tab::make('Sessions')
                                ->translateLabel()
                                ->badge(fn (Model $record): int => $record->workSessions->count())
                                ->schema([
                                    RepeatableEntry::make('workSessions')
                                        ->label('')
                                        ->columnSpanFull()
                                        ->getStateUsing(function (Model $record) {
                                            $items = $record->workSessions;
                                            foreach ($items as $key => $value) {
                                                $value->sort = $key;
                                            }

                                            return $items;
                                        })
                                        ->schema([
                                            Grid::make(6)->schema([
                                                TextEntry::make('start')
                                                    ->label('Started at')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->dateTime()
                                                    ->timezone(PejotaHelper::getUserTimeZone()),
                                                TextEntry::make('duration')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->icon('heroicon-o-clock')
                                                    ->formatStateUsing(fn ($state) => PejotaHelper::formatDuration($state)),
                                                TextEntry::make('title')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->columnSpan(fn (Model $record): int => $record->description ? 2 : 4),
                                                TextEntry::make('description')
                                                    ->hiddenLabel(fn ($record) => $record->sort != 0)
                                                    ->translateLabel()
                                                    ->html()
                                                    ->columnSpan(2)
                                                    ->visible(fn ($state) => $state ? true : false),
                                            ]),
                                        ]),
                                ]),

                            Tabs\Tab::make('History')
                                ->translateLabel()
                                ->badge(fn (Model $record): int => $record->activities->count())
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('created_at')
                                            ->translateLabel()
                                            ->inlineLabel()
                                            ->dateTime()
                                            ->timezone(PejotaHelper::getUserTimeZone()),
                                        TextEntry::make('updated_at')
                                            ->translateLabel()
                                            ->inlineLabel()
                                            ->dateTime()
                                            ->timezone(PejotaHelper::getUserTimeZone()),

                                    ]),

                                    RepeatableEntry::make('activities')
                                        ->translateLabel()
                                        ->columnSpanFull()
                                        ->contained(false)
                                        ->schema([
                                            Grid::make(4)->schema([
                                                TextEntry::make('created_at')
                                                    ->hiddenLabel()
                                                    ->icon('heroicon-o-chevron-double-right')
                                                    ->dateTime()
                                                    ->timezone(PejotaHelper::getUserTimeZone()),
                                                TextEntry::make('description')
                                                    ->hiddenLabel(),
                                                TextEntry::make('causer.name')
                                                    ->hiddenLabel(),
                                                TextEntry::make('properties.attributes')
                                                    ->hiddenLabel()
                                                    ->getStateUsing(
                                                        fn (Model $record): array => [$record->properties->get('attributes')['status.name']]
                                                    ),
                                            ]),
                                        ]),
                                ]),
                        ]),
                    ]),

                    /***************************
                     * Sidebar infolist with priority and status
                     ******************************/
                    Section::make([
                        Grid::make(2)->schema([
                            IconEntry::make('priority')
                                ->translateLabel()
                                ->icon(fn ($state) => PriorityEnum::from($state)->getIcon())
                                ->color(fn ($state) => PriorityEnum::from($state)->getColor())
                                ->tooltip(fn ($state) => PriorityEnum::from($state)->getLabel()),

                            TextEntry::make('status.name')
                                ->badge()
                                ->color(fn (Model $record): array => Color::hex($record->status->color))
                                ->hintActions([
                                    Action::make('change_status')
                                        ->hiddenLabel()
                                        ->icon('heroicon-o-pencil')
                                        ->color(Color::hex('#ACA'))
                                        ->button()
                                        ->tooltip(__('Change status'))
                                        ->action(function (Model $record, array $data): void {
                                            $record->update($data);
                                        })
                                        ->form([
                                            Forms\Components\Select::make('status_id')
                                                ->label('Status')
                                                ->options(fn (): array => Status::all()->pluck('name', 'id')->toArray()),
                                        ]),
                                ]),
                        ]),

                        TextEntry::make('due_date')
                            ->translateLabel()
                            ->date()
                            ->icon('heroicon-o-exclamation-triangle'),

                        TextEntry::make('effort')
                            ->translateLabel()
                            ->label('Estimated')
                            ->inlineLabel()
                            ->icon('heroicon-o-variable')
                            ->formatStateUsing(fn (Model $record): string => $record->effort.' '.$record->effort_unit),

                        TextEntry::make('workSessions')
                            ->label('Sessions')
                            ->translateLabel()
                            ->inlineLabel()
                            ->icon('heroicon-o-clock')
                            ->formatStateUsing(fn (Model $record): string => PejotaHelper::formatDuration($record->workSessions->sum('duration'))),

                        Actions::make([
                            Action::make('edit')
                                ->translateLabel()
                                ->url(
                                    fn (Model $record) => "{$record->id}/edit"
                                )
                                ->icon('heroicon-o-pencil'),

                            Action::make('list')
                                ->translateLabel()
                                ->url(
                                    fn (Model $record) => './.'
                                )
                                ->icon('heroicon-o-chevron-left')
                                ->color(Color::Neutral),

                            Action::make('session')
                                ->translateLabel()
                                ->icon(WorkSessionResource::getNavigationIcon())
                                ->color(Color::Amber)
                                ->modal(true)
                                ->url(fn ($record) => CreateWorkSession::getUrl([
                                    'task' => $record->id,
                                ])),
                        ]),
                    ])->grow(false),

                ])
                    ->columnSpanFull(),

            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'view' => Pages\ViewTask::route('/{record}'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }

    public static function getStateCompleted($component)
    {
        $index = explode('.', $component->getStatePath())[1];

        $data = $component
            ->getContainer()
            ->getParentComponent()
            ->getState()[$index];

        return $data['completed'];
    }
}
