# Personal Book Tracker — Filament v5 Learning Project

A beginner-friendly Filament v5 project: a personal reading list manager. It covers the core
Filament concepts — Resource CRUD, form components, table filters, status badges and dashboard
stats widgets — without overwhelming complexity.

**Stack:** Laravel 13 · Filament 5.9 · PHP 8.4 · MySQL
**Docs:** https://filamentphp.com/docs (make sure the version switcher says 5.x)

> Filament v5 differs from v3 tutorials: forms use `Filament\Schemas\Schema` (not `Form`),
> all actions live in `Filament\Actions\`, and generated resources are split into
> `Schemas/BookForm.php` and `Tables/BooksTable.php`.

## Data Model

**Book**

| Field     | Type                 | Notes                                    |
|-----------|----------------------|------------------------------------------|
| title     | string               | required                                 |
| author    | string               | required                                 |
| genre     | string (enum)        | Fiction, Non-Fiction, Tech, Sci-Fi       |
| status    | string (enum)        | Want to Read, Reading, Completed         |
| rating    | tinyint, nullable    | 1 to 5 stars                             |
| summary   | text, nullable       | textarea or rich editor                  |
| read_at   | date, nullable       | set when the book is completed           |

## Progress

### Phase 0 — Setup
- [x] Laravel project + MySQL database
- [x] Install Filament 5.9 and the admin panel (`/admin`)
- [x] Create admin user
- [x] Run `php artisan boost:install` (AI agent guidelines for this stack)
- [x] Set `APP_NAME="Personal Book Tracker"` in `.env`

### Phase 1 — Model & Data
- [x] `php artisan make:model Book -mfs` (model, migration, factory, seeder)
- [x] Migration with the fields above
- [x] `App\Enums\Genre` and `App\Enums\BookStatus` implementing `HasLabel` / `HasColor`
- [x] Cast `genre`, `status` to enums and `read_at` to `date` on the model
- [x] Factory + seeder (~20 fake books), run `php artisan migrate --seed`

### Phase 2 — Resource CRUD
- [x] `php artisan make:filament-resource Book -G --view`
- [x] Form: `TextInput` (title, author), `Select` (genre, status),
      `Textarea` (summary), `DatePicker` (read_at) — generated from the model casts
- [ ] Rating as a 1–5 `Select` (the generator made it a plain numeric `TextInput`)
- [ ] Swap the summary `Textarea` for a `RichEditor`
- [ ] Show rating + `read_at` only when status is Completed (`Get $get`)

### Phase 2b — Actions
- [x] `DeleteAction` on table rows (`->recordActions()`)
- [x] `DeleteAction` + `ViewAction` in the edit page header
- [x] Custom create notification (`getCreatedNotification()`)
- [ ] Custom save notification on the edit page
- [ ] `mutateFormDataBeforeCreate()` to clear rating/read_at on unfinished books

### Phase 3 — Table
- [x] Columns: title, author, genre, status, rating, read_at
- [x] Status as a colored badge (comes from the enum's `HasColor`)
- [x] Searchable title/author, sortable columns
- [ ] Filters: `SelectFilter` for status and genre, rating filter

### Phase 4 — Dashboard
- [ ] `php artisan make:filament-widget BookStats --stats-overview`
- [ ] Stats: total books, currently reading, completed, average rating
- [ ] Remove `FilamentInfoWidget` from the panel

### Phase 5 — Extras (optional)
- [ ] Chart widget (books completed per month)
- [ ] Book cover image (`FileUpload`)
- [ ] Per-user books (`user_id`) so each user sees only their own list
- [ ] Feature tests for the resource pages
