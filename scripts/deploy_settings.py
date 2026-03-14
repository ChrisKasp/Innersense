from ftplib import FTP
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent

config = {}
with open(REPO_ROOT / 'mariadb_migration' / 'server_credentials.conf', 'r') as f:
    for line in f:
        line = line.strip()
        if '=' in line and not line.startswith('#'):
            key, value = line.split('=', 1)
            config[key] = value

host = config['SERVER_HOST']
user = config['SERVER_USER']
password = config['SERVER_PASSWORD']

ftp = FTP(host)
ftp.login(user, password)
ftp.cwd('/')
print('Connected to server')

# Check if views directory exists
try:
    ftp.cwd('public/views')
    print('views/ directory exists')
except:
    ftp.cwd('/')
    try:
        ftp.cwd('public')
    except:
        ftp.mkd('public')
        ftp.cwd('public')
    try:
        ftp.cwd('views')
    except:
        ftp.mkd('views')
        ftp.cwd('views')
    print('Created views/ directory')

# Upload user management
with open(REPO_ROOT / 'public' / 'views' / 'user_management.php', 'rb') as f:
    ftp.storbinary('STOR user_management.php', f)
print('Uploaded views/user_management.php')

# Upload app settings
with open(REPO_ROOT / 'public' / 'views' / 'app_settings.php', 'rb') as f:
    ftp.storbinary('STOR app_settings.php', f)
print('Uploaded views/app_settings.php')

# Upload index.php
ftp.cwd('/')
ftp.cwd('public')
with open(REPO_ROOT / 'public' / 'index.php', 'rb') as f:
    ftp.storbinary('STOR index.php', f)
print('Uploaded public/index.php')

ftp.quit()
print('Deployment complete!')
