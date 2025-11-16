# Sekolah Management System

A high-performance school management system built with Laravel, featuring both web interface for administrators and REST API for mobile applications. **Optimized for production with advanced caching, database indexing, and performance monitoring.**

## 🚀 Performance Features

- **Redis Caching**: 5-minute cache for API responses
- **Database Optimization**: MySQL with connection pooling and persistent connections
- **Query Optimization**: Eager loading, selective field queries, and composite indexes
- **Memory Management**: Pagination limits and result set optimization
- **Error Handling**: Comprehensive logging and graceful error recovery
- **Mobile Optimization**: Lightweight endpoints for Android apps

## Features

### Web Interface
- **User Management**: Create, edit, delete users with role-based access (Admin, Kurikulum, etc.)
- **Teacher Management**: Manage teacher profiles and assignments
- **Subject Management**: Add and organize school subjects
- **Classroom Management**: Configure classrooms and capacities
- **Schedule Management**: Create and manage class schedules
- **Dashboard**: Real-time statistics and overview
- **Activity Logging**: Track all system activities

### API Endpoints
- **Authentication**: Login, logout, password change
- **Dropdown Data**: Subjects, teachers, classrooms for mobile app
- **Schedule Management**: CRUD operations for schedules
- **Reports**: Classroom utilization and monthly reports
- **Notifications**: System notifications management

## Quick Start

1. **Setup Database**:
   ```bash
   # Create database
   CREATE DATABASE db_sekolah;

   # Run migrations
   php artisan migrate

   # Seed data (optional)
   php artisan db:seed
   ```

2. **Start Server**:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

3. **Access Application**:
   - Web Interface: http://localhost:8000
   - API: http://localhost:8000/api

## Demo Credentials

- **Admin**: admin@gmail.com / 123456
- **Kurikulum**: kurikulum@gmail.com / 123456

## API Usage

### Base URL
```
http://localhost:8000/api
```

### Key Endpoints
- `GET /api/subjects` - Get all subjects
- `GET /api/teachers` - Get all teachers
- `GET /api/classrooms` - Get all classrooms
- `GET /api/schedules` - Get schedules
- `POST /api/auth/login` - User login

### For Mobile Apps
Use proper headers:
```
Accept: application/json
User-Agent: okhttp/4.x.x (or your mobile app identifier)
```

## Android Integration

The system includes comprehensive Android implementation examples:

- **Retrofit Setup**: Network configuration with OkHttp
- **Data Models**: Kotlin data classes for API responses
- **Repository Pattern**: Clean architecture for data handling
- **ViewModels**: MVVM pattern with LiveData
- **UI Components**: Material Design spinners and forms

See the Android implementation guide in the project documentation.

## 🚀 Performance Optimization

This system is optimized for high performance and stability:

### Database Optimizations
- **MySQL with Connection Pooling**: Persistent connections with 2-10 connection pool
- **Strategic Indexes**: Composite indexes on frequently queried columns
- **Query Optimization**: Eager loading and selective field queries
- **Soft Deletes**: Efficient data management without permanent deletion

### Caching Strategy
- **Redis Cache**: 5-minute TTL for API responses
- **Smart Cache Invalidation**: Automatic cache clearing on data changes
- **Multiple Cache Layers**: Database, API, and view caching

### API Optimizations
- **Pagination**: Configurable page sizes with maximum limits
- **Mobile-Specific Endpoints**: Lightweight responses for Android apps
- **Rate Limiting**: Built-in protection against abuse
- **Error Handling**: Comprehensive logging and graceful degradation

### Memory Management
- **Result Set Limits**: Maximum 50-100 records per request
- **Selective Loading**: Only necessary fields and relationships
- **Connection Timeouts**: 30-second timeouts to prevent hanging

## Production Deployment

### Prerequisites
```bash
# Install Redis
sudo apt-get install redis-server

# Install MySQL
sudo apt-get install mysql-server

# Configure PHP
sudo apt-get install php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-redis php8.2-mbstring php8.2-xml php8.2-curl
```

### Environment Setup
1. **Copy environment file**:
   ```bash
   cp .env.example .env
   ```

2. **Configure database**:
   ```bash
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=sekolah_api
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

3. **Configure Redis**:
   ```bash
   CACHE_STORE=redis
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   ```

4. **Generate application key**:
   ```bash
   php artisan key:generate
   ```

5. **Run migrations**:
   ```bash
   php artisan migrate --force
   ```

### Performance Tuning

#### PHP Configuration (`/etc/php/8.2/fpm/php.ini`)
```ini
memory_limit = 512M
max_execution_time = 60
upload_max_filesize = 10M
post_max_size = 10M
max_input_time = 60
```

#### MySQL Configuration (`/etc/mysql/mysql.conf.d/mysqld.cnf`)
```ini
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
max_connections = 100
query_cache_size = 64M
query_cache_limit = 8M
```

#### Redis Configuration (`/etc/redis/redis.conf`)
```ini
save 900 1
save 300 10
save 60 10000
maxmemory 512mb
maxmemory-policy allkeys-lru
```

### Web Server Setup (Nginx)
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/sekolah-api/public;

    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Monitoring and Maintenance

#### Daily Tasks
```bash
# Clear expired cache
php artisan cache:prune

# Optimize database
php artisan optimize

# Clear logs older than 7 days
find storage/logs -name "*.log" -type f -mtime +7 -delete
```

#### Weekly Tasks
```bash
# Analyze database performance
php artisan db:monitor

# Optimize database tables
php artisan tinker --execute="DB::statement('OPTIMIZE TABLE schedules, users, classes');"
```

## Troubleshooting

### Common Issues

1. **Database Connection**:
   - Ensure MySQL is running
   - Check `.env` file for correct database credentials

2. **Server Not Starting**:
   ```bash
   # Clear caches
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

3. **Permission Issues**:
   - Ensure `storage` and `bootstrap/cache` are writable

4. **Performance Issues**:
   ```bash
   # Check cache status
   php artisan cache:status

   # Monitor database queries
   php artisan db:monitor

   # Clear all caches
   php artisan optimize:clear
   ```

### Performance Monitoring
- **Database**: Check slow queries in MySQL logs
- **Cache**: Monitor Redis memory usage
- **API**: Use Laravel Telescope for request monitoring
- **Server**: Monitor CPU, memory, and disk usage

## Project Structure

```
sekolah-api/
├── app/                    # Application logic
│   ├── Http/Controllers/   # Web and API controllers
│   ├── Models/             # Eloquent models
│   └── Services/           # Business logic services
├── database/migrations/    # Database schema
├── resources/views/        # Blade templates
├── routes/                 # Route definitions
└── public/                 # Public assets
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open-sourced software licensed under the MIT license.
