# Laravel Admin Panel

An advanced Laravel Admin Panel with comprehensive meta information management, SEO analysis, and dual authentication system.

## Features

### Authentication System
- **Dual Authentication**: Separate admin and user authentication guards
- **Admin Login**: Custom admin authentication with middleware protection
- **Profile Management**: Update profile, change password functionality
- **Security**: Active user validation, secure password hashing

### Page Management
- **CRUD Operations**: Complete page management system
- **Meta Information**: Comprehensive SEO meta data support
- **Polymorphic Relations**: Flexible meta information system
- **Status Management**: Draft/Published page states
- **User Attribution**: Track page creators and editors

### SEO & Meta Information
- **Advanced Meta Fields**:
  - Basic Meta (title, description, keywords)
  - Open Graph (Facebook sharing)
  - Twitter Cards (Twitter sharing)
  - Canonical URLs, Robots meta
- **Real-time SEO Analysis**: Live scoring and optimization suggestions
- **Meta Previews**: Google, Facebook, Twitter preview components
- **Character Counters**: Color-coded optimization hints

### UI Components
- **Shadcn-inspired Design**: Modern, clean interface
- **Responsive Layout**: Mobile-first design approach
- **Interactive Components**: Modals, dropdowns, alerts
- **Real-time Features**: Character counting, SEO scoring
- **Accessibility**: Proper ARIA labels and keyboard navigation

## Project Structure

```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   │   ├── AuthController.php      # Admin authentication
│   │   │   │   ├── DashboardController.php # Admin dashboard
│   │   │   │   └── PageController.php      # Page management
│   │   │   └── HomeController.php          # Frontend controller
│   │   └── Middleware/
│   │       └── AdminAuth.php               # Admin route protection
│   ├── Models/
│   │   ├── Admin.php                       # Admin user model
│   │   ├── Page.php                        # Page model
│   │   ├── MetaInformation.php             # Meta information model
│   │   └── SiteSetting.php                 # Site settings model
│   └── Services/
│       └── SEOAnalyzerService.php          # SEO analysis service
├── database/
│   ├── factories/
│   │   ├── AdminFactory.php                # Admin test data factory
│   │   ├── PageFactory.php                 # Page test data factory
│   │   └── MetaInformationFactory.php      # Meta test data factory
│   ├── migrations/
│   │   ├── create_admins_table.php         # Admin users table
│   │   ├── create_pages_table.php          # Pages table
│   │   ├── create_meta_information_table.php # Meta information table
│   │   └── create_site_settings_table.php  # Site settings table
│   └── seeders/
│       └── AdminSeeder.php                 # Default admin user
├── resources/
│   └── views/
│       ├── admin/                          # Admin panel views
│       │   ├── layouts/
│       │   │   └── admin.blade.php         # Admin master layout
│       │   ├── auth/
│       │   │   └── login.blade.php         # Admin login form
│       │   ├── dashboard.blade.php         # Admin dashboard
│       │   └── pages/                      # Page management views
│       ├── components/
│       │   └── admin/                      # Reusable admin components
│       │       ├── button.blade.php        # Button component
│       │       ├── card.blade.php          # Card component
│       │       ├── input.blade.php         # Input component
│       │       ├── character-counter.blade.php # Character counter
│       │       ├── seo-score.blade.php     # SEO score widget
│       │       └── meta-preview.blade.php  # Meta preview components
│       └── home.blade.php                  # Custom homepage
└── tests/
    ├── Feature/                            # Feature tests
    │   ├── AdminAuthTest.php               # Admin authentication tests
    │   ├── AdminDashboardTest.php          # Dashboard functionality tests
    │   └── PageManagementTest.php          # Page CRUD tests
    └── Unit/                               # Unit tests
        ├── ModelTest.php                   # Model relationship tests
        └── SEOAnalyzerTest.php             # SEO service tests
```

## Installation & Setup

### Prerequisites
- PHP 8.2 or higher
- Composer
- SQLite (default) or MySQL/PostgreSQL
- Node.js & npm (for frontend assets)

### Step 1: Clone & Install Dependencies
```bash
git clone <repository-url>
cd laravel-admin-panel
composer install
npm install
```

### Step 2: Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` file:
```env
APP_NAME="Admin Panel"
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

### Step 3: Database Setup
```bash
# Create SQLite database file (if not exists)
touch database/database.sqlite

# Run migrations
php artisan migrate

# Seed default admin user
php artisan db:seed --class=AdminSeeder
```

### Step 4: Build Frontend Assets
```bash
npm run build
# or for development
npm run dev
```

### Step 5: Start Development Server
```bash
php artisan serve
```

Visit `http://localhost:8000` to see the custom homepage.

## Default Credentials

### Admin Login
- **URL**: `/admin/login`
- **Email**: `admin@example.com`
- **Password**: `password`

## Available Models & Relationships

### Admin Model
```php
// Relationships
- hasMany(Page::class, 'created_by')
- hasMany(Page::class, 'updated_by')

// Key Features
- Multi-guard authentication
- Active status management
- Role-based access
```

### Page Model
```php
// Relationships
- belongsTo(Admin::class, 'created_by')
- belongsTo(Admin::class, 'updated_by')
- morphOne(MetaInformation::class, 'metable')

// Key Features
- Auto-generated slugs
- Draft/Published status
- Breadcrumb control
- SEO integration
```

### MetaInformation Model
```php
// Relationships
- morphTo('metable') // Polymorphic relationship

// Key Features
- Complete SEO meta support
- Open Graph integration
- Twitter Cards support
- Canonical URL management
```

### SiteSetting Model
```php
// Key Features
- Default meta values
- Site-wide configuration
- SEO defaults
```

## API Routes

### Admin Routes (Protected by admin middleware)
```php
Route::prefix('admin')->name('admin.')->group(function () {
    // Authentication
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Pages Management
    Route::resource('pages', PageController::class);
});
```

### Frontend Routes
```php
Route::get('/', [HomeController::class, 'index'])->name('home');
```

## Testing

### Run Tests
```bash
# Run all tests
./vendor/bin/pest

# Run specific test suite
./vendor/bin/pest tests/Feature/
./vendor/bin/pest tests/Unit/

# Run with coverage
./vendor/bin/pest --coverage
```

### Test Coverage
- **Feature Tests**: Admin authentication, dashboard, page management
- **Unit Tests**: Model relationships, SEO analyzer service
- **Factory Support**: Test data generation for all models

## SEO Analyzer Service

### Features
- **Title Analysis**: Optimal length checking (50-60 characters)
- **Description Analysis**: Meta description optimization (150-160 characters)
- **Content Analysis**: Word count and quality assessment
- **Keyword Analysis**: Keyword density and count validation
- **Readability Check**: Sentence length analysis
- **Scoring System**: 0-100 point scoring with grades

### Usage Example
```php
$seoAnalyzer = new SEOAnalyzerService();
$result = $seoAnalyzer->analyzePage($title, $description, $content, $keywords);

// Result structure:
[
    'score' => 85,
    'grade' => 'good',
    'checks' => [...],
    'suggestions' => [...]
]
```

## Component Library

### Available Components
- `<x-admin.button>` - Styled buttons with variants
- `<x-admin.card>` - Content cards with headers
- `<x-admin.input>` - Form inputs with validation
- `<x-admin.select>` - Dropdown selectors
- `<x-admin.alert>` - Status messages
- `<x-admin.character-counter>` - Character counting with optimization hints
- `<x-admin.seo-score>` - Real-time SEO scoring widget
- `<x-admin.meta-preview>` - Social media preview components

### Usage Example
```blade
<x-admin.character-counter 
    :current="strlen($title)" 
    :min="50" 
    :max="60" 
    :optimal-min="50" 
    :optimal-max="60" 
/>
```

## Development Commands

### Useful Laravel Commands
```bash
# Generate new migration
php artisan make:migration create_table_name

# Generate new model with factory
php artisan make:model ModelName -mf

# Generate new controller
php artisan make:controller Admin/ControllerName

# Clear application cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Generate application key
php artisan key:generate
```

### Database Commands
```bash
# Reset database and re-run migrations
php artisan migrate:fresh

# Run migrations with seeders
php artisan migrate:fresh --seed

# Run specific seeder
php artisan db:seed --class=AdminSeeder
```

## Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **Password Hashing**: Bcrypt password encryption
- **Input Validation**: Server-side validation for all inputs
- **XSS Protection**: Blade template escaping
- **Admin Middleware**: Route protection for admin areas
- **Active User Check**: Only active admins can access system

## Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/new-feature`)
3. Commit changes (`git commit -am 'Add new feature'`)
4. Push to branch (`git push origin feature/new-feature`)
5. Create Pull Request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

For support and questions:
- Check the documentation above
- Review the code comments in the source files
- Examine the test files for usage examples
- Check Laravel documentation for framework-specific questions
