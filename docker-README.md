# Docker Development Environment for GoLightPay WooCommerce Plugin

This document describes how to set up and use the Docker development environment for the GoLightPay WooCommerce plugin.

## Prerequisites

- Docker (version 20.10 or higher)
- Docker Compose (version 2.0 or higher)

## Quick Start

### 1. Start the Environment

```bash
docker-compose up -d
```

This will start:

- **WordPress** (accessible at http://localhost:8081)
- **MySQL Database** (internal, not exposed)
- **phpMyAdmin** (accessible at http://localhost:8082)

### 2. Access WordPress

1. Open http://localhost:8081 in your browser
2. Complete the WordPress installation wizard
3. Install WooCommerce plugin
4. Activate the GoLightPay plugin

### 3. Access phpMyAdmin

- URL: http://localhost:8082
- Server: `db`
- Username: `wordpress`
- Password: `wordpress`

## Configuration

### Environment Variables

The Docker Compose file includes the following default configurations:

**WordPress:**

- Database Host: `db`
- Database User: `wordpress`
- Database Password: `wordpress`
- Database Name: `wordpress`
- Debug Mode: Enabled

**MySQL:**

- Root Password: `rootpassword`
- Database: `wordpress`
- User: `wordpress`
- Password: `wordpress`

**phpMyAdmin:**

- Host: `db`
- User: `wordpress`
- Password: `wordpress`

### Port Configuration

- **WordPress**: Port 8081 (host) → Port 80 (container)
- **phpMyAdmin**: Port 8082 (host) → Port 80 (container)
- **MySQL**: Not exposed externally (internal access only)

To change ports, modify the `ports` section in `docker-compose.yml`:

```yaml
wordpress:
  ports:
    - "8081:80" # Change 8081 to your desired port
```

## Development Workflow

### Plugin Development

The plugin directory is mounted as a volume, so changes to PHP files are immediately reflected:

```yaml
volumes:
  - ./:/var/www/html/wp-content/plugins/woocommerce-golightpay
```

**Note**: After modifying PHP files, you may need to clear WordPress cache or refresh the page.

### Viewing Logs

```bash
# View all logs
docker-compose logs

# View WordPress logs
docker-compose logs wordpress

# View database logs
docker-compose logs db

# Follow logs in real-time
docker-compose logs -f wordpress
```

### Accessing Containers

```bash
# Enter WordPress container
docker exec -it golightpay-wp bash

# Enter database container
docker exec -it golightpay-db bash

# Access MySQL CLI
docker exec -it golightpay-db mysql -u wordpress -pwordpress wordpress
```

## Common Operations

### Stop Containers

```bash
docker-compose stop
```

### Start Containers

```bash
docker-compose start
```

### Restart Containers

```bash
docker-compose restart
```

### Stop and Remove Containers

```bash
docker-compose down
```

### Stop and Remove Containers + Volumes (⚠️ Deletes all data)

```bash
docker-compose down -v
```

### Rebuild Containers

```bash
docker-compose up -d --build
```

## Database Management

### Backup Database

```bash
docker exec golightpay-db mysqldump -u wordpress -pwordpress wordpress > backup.sql
```

### Restore Database

```bash
docker exec -i golightpay-db mysql -u wordpress -pwordpress wordpress < backup.sql
```

### Reset WordPress Password

**Method 1: Using WP-CLI**

```bash
docker exec -it golightpay-wp wp user update admin --user_pass=newpassword --allow-root
```

**Method 2: Using MySQL**

```bash
# Enter MySQL
docker exec -it golightpay-db mysql -u wordpress -pwordpress wordpress

# Execute SQL (replace 'newpassword' with your desired password)
UPDATE wp_users SET user_pass = MD5('newpassword') WHERE user_login = 'admin';
```

**Method 3: Using phpMyAdmin**

1. Access http://localhost:8082
2. Select `wordpress` database
3. Open `wp_users` table
4. Edit the admin user
5. Set password (MD5 hash)

## Troubleshooting

### Port Already in Use

If port 8081 or 8082 is already in use:

1. Check which process is using the port:

   ```bash
   sudo lsof -i :8081
   sudo lsof -i :8082
   ```

2. Change the port in `docker-compose.yml`

### WordPress Cannot Connect to Database

1. Check if the database container is running:

   ```bash
   docker-compose ps
   ```

2. Check database logs:

   ```bash
   docker-compose logs db
   ```

3. Verify environment variables in `docker-compose.yml`

### Plugin Not Appearing

1. Ensure the plugin directory is correctly mounted
2. Check file permissions:
   ```bash
   docker exec golightpay-wp ls -la /var/www/html/wp-content/plugins/
   ```
3. Verify the plugin is in the correct location

### Clear WordPress Cache

```bash
# Clear object cache
docker exec -it golightpay-wp wp cache flush --allow-root

# Clear rewrite rules
docker exec -it golightpay-wp wp rewrite flush --allow-root
```

## Production Deployment

For production deployment, consider:

1. **Security**:

   - Change all default passwords
   - Use strong database passwords
   - Disable debug mode
   - Use HTTPS

2. **Performance**:

   - Use a reverse proxy (Nginx/Apache)
   - Enable caching
   - Optimize database

3. **Backup**:

   - Set up regular database backups
   - Backup WordPress files

4. **Monitoring**:
   - Set up log monitoring
   - Monitor container health

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [WordPress Docker Image](https://hub.docker.com/_/wordpress)
- [WooCommerce Documentation](https://woocommerce.com/documentation/)

## Support

For issues related to the GoLightPay plugin, please refer to:

- Plugin documentation
- GitHub Issues
- Support email: support@golightpay.com
