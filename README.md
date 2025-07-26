# client-projects-manager

A custom WordPress plugin for managing client projects with a full admin UI, frontend display via shortcode, AJAX filtering, and REST API access.

## Features

- Custom Post Type: 'Client Project'
- Custom Fields: Client Name, Project Status, Deadline
- Admin UI with sortable and filterable columns
- Shortcode '[client_projects]' to show projects on frontend
- AJAX filter by project status (dropdown UI)
- Color-coded status labels in admin
- REST API endpoint to get project data

## How to Use

1. Upload the plugin to '/wp-content/plugins/client-projects-manager/'
2. Activate it from the Plugins page
3. Add or edit projects via 'Dashboard → Client Projects'
4. Use shortcode '[client_projects]'in any page/post to display project grid

## REST API Endpoint

GET: '/wp-json/cpm/v1/projects'  
Optional query param: '?status=Ongoing', '?status=Pending', etc.

## Frontend Output

Responsive grid of projects, filtered dynamically via AJAX. Each card shows:
- Project title
- Client name
- Status
- Deadline
- Description

## Admin Features

- Filter projects by status in the dashboard
- Sortable columns for status and deadline
- Colored labels for better visibility

## Files Included

- 'client-projects-manager.php' — main plugin file
- 'ajax.js' — handles frontend AJAX filtering

## License

This plugin is open-sourced for assessment purposes.
