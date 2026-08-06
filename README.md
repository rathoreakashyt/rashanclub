# Laravel & Vue.js Project Documentation

## Prerequisites
- PHP >= 8.2
- Composer
- Node.js >= 16.x
- MySQL
- XAMPP (for local development)

## Project Setup

### 1. Clone the Repository
```bash
git clone <repository-url>
cd off_pos_laravel
```

### 2. Backend Setup (Laravel)

#### Install PHP Dependencies
```bash
composer install
```

#### Environment Configuration
1. Copy the environment file:
```bash
cp .env.example .env
```

2. Generate application key:
```bash
php artisan key:generate
```

3. Configure your database in `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

#### Database Setup
1. Create a new database in MySQL
2. Run migrations:
```bash
php artisan migrate:fresh --seed
```

## Running the Application

### Start Backend Server

The Laravel application will be available at `http://localhost/off_pos_laravel`



## Common Issues and Solutions

### Database Connection Issues
- Ensure MySQL service is running
- Verify database credentials in `.env`
- Check if database exists


### Laravel Issues
- Clear Laravel cache:
  ```bash
  php artisan config:clear
  php artisan cache:clear
  php artisan view:clear
  ```

### Git Workflow
1. Create feature branch from develop
2. Make changes and commit
3. Push to remote
4. Create pull request


2. Configure production environment
3. Set up web server (Apache/Nginx)
4. Configure SSL certificates
5. Set up CI/CD pipeline

## Support
For any issues or questions, please contact the development team or create an issue in the repository.
