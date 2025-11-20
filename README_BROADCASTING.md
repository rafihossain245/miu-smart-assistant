# Real-time Broadcasting Setup

This project includes real-time broadcasting functionality for live updates of source processing status and chat interactions.

## Quick Setup Guide

### Option 1: Laravel Reverb (Recommended - Free)

1. **Configure Broadcasting**: Visit `/admin/settings/broadcast` in your admin panel
2. **Set Driver**: Choose "Laravel Reverb"
3. **Configure Settings**:
   - App ID: `my-app-id`
   - Key: `my-app-key`
   - Secret: `my-app-secret`
   - Host: `localhost`
   - Port: `8080`
   - Scheme: `http`
4. **Enable Broadcasting**: Check the "Enable Broadcasting" checkbox
5. **Save Settings**

6. **Start Reverb Server**:
```bash
php artisan reverb:start
```

### Option 2: Pusher (Cloud Service)

1. **Create Pusher Account**: Sign up at [pusher.com](https://pusher.com)
2. **Create New App**: Get your credentials from the dashboard
3. **Configure in Admin Panel**: Visit `/admin/settings/broadcast`
4. **Set Driver**: Choose "Pusher"
5. **Enter Credentials**:
   - App ID: Your Pusher App ID
   - Key: Your Pusher Key
   - Secret: Your Pusher Secret
   - Cluster: Your Pusher Cluster (e.g., mt1)
6. **Enable Broadcasting**: Check the checkbox and save

## Features

### Real-time Source Processing
- **Live Status Updates**: See processing status change in real-time
- **Progress Notifications**: Get instant feedback when sources complete or fail
- **Automatic UI Updates**: No need to refresh the page

### Connection Status Indicators
- **Sources Page**: Shows connection status with colored indicator
- **Chat Page**: Displays connection status in header
- **Automatic Reconnection**: Handles connection drops gracefully

### Events Broadcasted
- `source.processing.started` - When source processing begins
- `source.processing.completed` - When source processing succeeds
- `source.processing.failed` - When source processing fails

## Development

### Running with Reverb
```bash
# Terminal 1: Start Laravel
php artisan serve

# Terminal 2: Start Queue Worker
php artisan queue:work

# Terminal 3: Start Reverb
php artisan reverb:start
```

### Testing Real-time Updates
1. Go to your chatbot's knowledge base
2. Add a new source (URL, PDF, etc.)
3. Watch the status update in real-time without page refresh
4. Check the connection indicator (green = connected, red = disconnected)

## Troubleshooting

### Connection Issues
- Ensure Reverb server is running (`php artisan reverb:start`)
- Check firewall settings for port 8080
- Verify broadcasting is enabled in admin settings
- Check browser console for WebSocket errors

### No Real-time Updates
- Verify queue worker is running (`php artisan queue:work`)
- Check broadcasting configuration in admin panel
- Ensure source processing jobs are being dispatched
- Check Laravel logs for broadcast errors

### Performance
- Reverb is lightweight and perfect for development/small deployments
- For production with high traffic, consider Pusher or Redis broadcasting
- Monitor WebSocket connection limits

## Security

### Channel Authorization
- Private channels are used (`chatbot.{id}`)
- Users can only listen to their own chatbot channels
- Authorization happens via Laravel's broadcast auth system

### Configuration Storage
- All broadcast settings are stored securely in database
- Sensitive credentials are not exposed to frontend
- API endpoint only returns necessary connection details

## Architecture

```
Frontend (React + Laravel Echo)
    ↓ WebSocket Connection
Laravel Reverb/Pusher Server
    ↓ Event Broadcasting
Laravel Backend
    ↓ Job Processing
Queue Worker → Database Updates → Broadcast Events
```

The system provides a seamless real-time experience while maintaining security and performance.