#!/usr/bin/env python3
"""Upload only locally changed files to the all-inkl web server via FTP."""

from __future__ import annotations

import argparse
import subprocess
import sys
from ftplib import FTP, error_perm
from pathlib import Path, PurePosixPath
from typing import Iterable, List

REPO_ROOT = Path(__file__).resolve().parent.parent
CREDENTIALS_FILE = REPO_ROOT / 'mariadb_migration' / 'server_credentials.conf'

# Welche Dateitypen sollen standardmäßig hochgeladen werden?
ALLOWED_EXTENSIONS = {
    '.php', '.html', '.htm', '.css', '.js', '.json',
    '.png', '.jpg', '.jpeg', '.webp', '.gif', '.svg', '.avif',
}

# Verzeichnisse/Dateien, die beim Upload ignoriert werden sollen
EXCLUDE_DIR_PREFIXES = [
    'mariadb_migration/',
    'migrations/',
    'VBA-Codeelemente/',
    'screenshots/',
    'scripts/',
    'sql/',
    'docs/',
    'public/var/',
    'public/Bilder/Patenkinder/',
    '.git/',
]
EXCLUDE_FILENAMES = {
    '.DS_Store',
    '.env',
    'deploy_changed_files.py',
}

BINARY_EXTENSIONS = {'.jpg', '.jpeg', '.png', '.gif', '.pdf', '.zip', '.gz', '.bz2', '.tar', '.tgz', '.webp', '.svg', '.avif'}


def load_credentials() -> dict:
    if not CREDENTIALS_FILE.exists():
        raise FileNotFoundError(f"Credentials file not found: {CREDENTIALS_FILE}")
    config: dict[str, str] = {}
    for line in CREDENTIALS_FILE.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        key, value = line.split('=', 1)
        config[key.strip()] = value.strip()
    return config


def detect_changed_files(include_untracked: bool = False) -> List[Path]:
    """Parse `git status --porcelain` to find modified files."""
    cmd = ['git', 'status', '--porcelain']
    result = subprocess.run(cmd, cwd=REPO_ROOT, capture_output=True, text=True, check=True)
    changed: List[Path] = []
    for raw_line in result.stdout.splitlines():
        if not raw_line:
            continue
        status = raw_line[:2]
        index_status = status[0]
        worktree_status = status[1]
        path_part = raw_line[3:]
        if ' -> ' in path_part:  # renamed file
            path_part = path_part.split(' -> ', 1)[1]
        path = Path(path_part.strip())
        if not path:
            continue
        if status.startswith('??'):
            if not include_untracked:
                continue
        else:
            # Nur nicht-gestagte Änderungen: Index muss leer sein
            if index_status != ' ':
                continue
            # Keine Arbeitsbaum-Änderung => überspringen
            if worktree_status == ' ':
                continue
            # Gelöschte Dateien nicht hochladen
            if worktree_status == 'D':
                continue
        if status.strip() == '':
            continue
        full_path = REPO_ROOT / path
        if full_path.is_file():
            changed.append(path)
    return changed


def should_upload(path: Path) -> bool:
    """Entscheidet, ob eine Datei für den Web-Upload relevant ist."""
    # Exakte Dateinamen ausschließen
    if path.name in EXCLUDE_FILENAMES:
        return False

    rel_posix = path.as_posix()

    # Allow uploading files under public/scripts/ (web-exposed helper scripts)
    # This keeps top-level `scripts/` excluded but permits `public/scripts/`.
    if rel_posix.startswith('public/scripts/'):
        return True

    # Offensichtlich nicht web-relevante Verzeichnisse ausschließen
    for prefix in EXCLUDE_DIR_PREFIXES:
        if rel_posix.startswith(prefix):
            return False

    # Versteckte Dateien (außer .env) überspringen
    if path.name.startswith('.') and path.name != '.env':
        return False

    # Nur bestimmte Endungen zulassen
    if path.suffix.lower() not in ALLOWED_EXTENSIONS:
        return False

    return True


def ensure_remote_base(ftp: FTP, desired: str) -> str:
    """Ensure base directory exists and return its absolute path."""
    desired = desired.strip() or '/'
    try:
        ftp.cwd(desired)
        return ftp.pwd()
    except error_perm:
        pass
    ftp.cwd('/')
    parts = [p for p in desired.strip('/').split('/') if p]
    for part in parts:
        try:
            ftp.cwd(part)
        except error_perm:
            ftp.mkd(part)
            ftp.cwd(part)
    return ftp.pwd()


def upload_file(ftp: FTP, base_dir: str, rel_path: Path, dry_run: bool = False, remote_rel: Path | None = None) -> None:
    local_rel = rel_path
    remote_rel = remote_rel or rel_path
    rel_posix = remote_rel.as_posix()
    local_file = REPO_ROOT / local_rel
    if dry_run:
        # Simuliere nur den Zielpfad ohne FTP-Operationen
        remote_path = base_dir.rstrip('/') + '/' + rel_posix
        print(f"[DRY] {rel_posix} -> {remote_path}")
    else:
        remote_dir_parts = remote_rel.parent.parts
        ftp.cwd(base_dir)
        for part in remote_dir_parts:
            if not part:
                continue
            try:
                ftp.cwd(part)
            except error_perm:
                ftp.mkd(part)
                ftp.cwd(part)
        remote_path = ftp.pwd().rstrip('/') + '/' + remote_rel.name
        # FTP text mode (storlines) can fail on long lines and is not required here.
        # Use binary mode consistently for reliable transfer of all file types.
        mode = 'binary'
        with open(local_file, 'rb') as handle:
            ftp.storbinary(f'STOR {remote_rel.name}', handle)
        print(f"  ✓ {rel_posix} -> {remote_path} ({mode})")
        # Zur Basis zurück
        ftp.cwd(base_dir)


def adjust_upload_target(base_dir: str, rel_path: Path) -> tuple[str, Path]:
    """Map repository paths to server targets when base path is /public."""
    base = PurePosixPath(base_dir)
    if base.name == 'public' and rel_path.parts:
        first = rel_path.parts[0]
        if first == 'public':
            if len(rel_path.parts) == 1:
                return base_dir, Path('.')
            # Upload into /public without duplicating the public/ prefix.
            return base_dir, Path(*rel_path.parts[1:])

        # Any non-public top-level path (e.g. src/, vendor/) belongs next to
        # /public and must therefore be uploaded relative to parent(/public).
        parent = str(base.parent)
        return parent or '/', rel_path
    return base_dir, rel_path


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description='Upload changed files via FTP.')
    parser.add_argument('files', nargs='*', help='Optional explicit file list (relative to repo root).')
    parser.add_argument('--include-untracked', dest='include_untracked', action='store_true', default=False,
                        help='Include untracked files (e.g. new files not committed yet).')
    parser.add_argument('--no-untracked', dest='include_untracked', action='store_false',
                        help='Skip untracked files (default).')
    parser.add_argument('--all', action='store_true',
                        help='Upload all web files under public/.')
    parser.add_argument('--allow-any', action='store_true',
                        help='Upload explicit files even if normally excluded.')
    parser.add_argument('--dry-run', action='store_true', help='Show what would be uploaded without transferring.')
    parser.add_argument('--sync-bilder', action='store_true',
                        help='Sync public/assets/Bilder (upload all local files and delete stale remote files).')
    return parser.parse_args()


def list_public_files() -> List[Path]:
    public_dir = REPO_ROOT / 'public'
    if not public_dir.exists():
        return []
    return [p.relative_to(REPO_ROOT) for p in public_dir.rglob('*') if p.is_file()]


def list_src_files() -> List[Path]:
    src_dir = REPO_ROOT / 'src'
    if not src_dir.exists():
        return []
    return [p.relative_to(REPO_ROOT) for p in src_dir.rglob('*') if p.is_file()]


def find_bilder_dir() -> Path | None:
    candidates = [
        REPO_ROOT / 'public' / 'assets' / 'Bilder',
        REPO_ROOT / 'public' / 'assets' / 'bilder',
    ]
    for candidate in candidates:
        if candidate.exists() and candidate.is_dir():
            return candidate
    return None


def sync_bilder_folder(ftp: FTP, resolved_base: str, dry_run: bool) -> None:
    bilder_dir = find_bilder_dir()
    if bilder_dir is None:
        print('Kein Bilder-Ordner gefunden (erwartet: public/assets/Bilder oder public/assets/bilder).')
        return

    local_files = sorted([p for p in bilder_dir.iterdir() if p.is_file()])
    if not local_files:
        print(f'Keine lokalen Dateien in {bilder_dir.relative_to(REPO_ROOT).as_posix()} gefunden.')
        return

    local_rel_files = [p.relative_to(REPO_ROOT) for p in local_files]
    local_names = [p.name for p in local_files]
    remote_dir = resolved_base.rstrip('/') + '/' + bilder_dir.relative_to(REPO_ROOT).as_posix()

    print('Bilder-Sync Dateien (lokal):')
    for rel in local_rel_files:
        print(f'  - {rel.as_posix()}')
    print()

    if dry_run:
        print(f"[DRY] Würde Ordner synchronisieren: {bilder_dir.relative_to(REPO_ROOT).as_posix()} -> {remote_dir}")
        return

    remote_abs = ensure_remote_base(ftp, remote_dir)
    ftp.cwd(remote_abs)

    remote_names: List[str] = []
    for item in ftp.nlst():
        name = PurePosixPath(item).name
        if name in {'.', '..'}:
            continue
        remote_names.append(name)

    print(f'Upload zu {remote_abs}:')
    for rel_file in local_rel_files:
        upload_file(ftp, resolved_base, rel_file, dry_run=False)

    stale = sorted(set(remote_names) - set(local_names))
    if stale:
        ftp.cwd(remote_abs)
        print('Lösche alte Dateien auf dem Server:')
        for name in stale:
            deleted = False
            last_error: Exception | None = None
            delete_candidates = [name, remote_abs.rstrip('/') + '/' + name]
            for candidate in delete_candidates:
                try:
                    ftp.delete(candidate)
                    print(f'  - {name}')
                    deleted = True
                    break
                except error_perm as exc:
                    last_error = exc
            if not deleted:
                print(f'  ! Konnte nicht löschen: {name} ({last_error})')
    else:
        print('Keine alten Dateien zum Löschen gefunden.')


def main() -> None:
    args = parse_args()

    if args.sync_bilder:
        config = load_credentials()
        host = config.get('SERVER_HOST', '')
        user = config.get('SERVER_USER', '')
        password = config.get('SERVER_PASSWORD', '')

        configured = config.get('SERVER_PATH') or config.get('DOCUMENT_ROOT') or '/'
        if configured.startswith('/www/htdocs/'):
            base_dir = '/'
        else:
            base_dir = configured or '/'

        if not all([host, user, password]):
            raise RuntimeError('SERVER_HOST/USER/PASSWORD müssen in server_credentials.conf gesetzt sein.')

        if args.dry_run:
            ftp = None
            resolved_base = base_dir or '/'
            print(f"[DRY] Würde zu FTP {host} verbinden und den Bilder-Ordner synchronisieren.")
            sync_bilder_folder(ftp, resolved_base, dry_run=True)
            return

        ftp = FTP(host)
        ftp.login(user, password)
        print(f"Verbunden mit {host} als {user}")
        try:
            resolved_base = ensure_remote_base(ftp, base_dir)
            print(f"Remote-Basis: {resolved_base}")
            sync_bilder_folder(ftp, resolved_base, dry_run=False)
        finally:
            ftp.quit()
        return

    if args.files:
        files = [Path(f) for f in args.files]
    elif args.all:
        files = list_public_files() + list_src_files()
    else:
        files = detect_changed_files(include_untracked=args.include_untracked)
        # If no changed files were detected, fallback to including untracked
        # files automatically — this helps when new assets were just created
        # locally (e.g. generated favicons) but not yet committed.
        if not files:
            print('Keine geänderten Dateien gefunden. Versuche, untracked Dateien zu erfassen...')
            files = detect_changed_files(include_untracked=True)
    if args.allow_any and args.files:
        files = [f for f in files if (REPO_ROOT / f).is_file()]
    else:
        files = [f for f in files if (REPO_ROOT / f).is_file() and should_upload(f)]
    if not files:
        print('Keine geänderten Dateien gefunden.')
        return

    print('Dateien zum Upload:')
    for f in files:
        print(f"  - {f.as_posix()}")
    print()

    config = load_credentials()
    host = config.get('SERVER_HOST', '')
    user = config.get('SERVER_USER', '')
    password = config.get('SERVER_PASSWORD', '')

    # Basisverzeichnis aus Konfiguration lesen
    # Hinweis: Bei all-inkl zeigt das FTP-Root bereits auf das physische
    # /www/htdocs/<account>. In diesem Fall wäre ein SERVER_PATH wie
    # "/www/htdocs/w020a760" falsch interpretiert. Wir prüfen daher und
    # mappen solche Pfade zurück auf das FTP-Root "/".
    configured = config.get('SERVER_PATH') or config.get('DOCUMENT_ROOT') or '/'
    if configured.startswith('/www/htdocs/'):
        base_dir = '/'
    else:
        base_dir = configured or '/'

    if not all([host, user, password]):
        raise RuntimeError('SERVER_HOST/USER/PASSWORD müssen in server_credentials.conf gesetzt sein.')

    if args.dry_run:
        print(f"[DRY] Würde zu FTP {host} verbinden und Dateien übertragen.")
        ftp = None
    else:
        ftp = FTP(host)
        ftp.login(user, password)
        print(f"Verbunden mit {host} als {user}")

    try:
        if args.dry_run:
            resolved_base = base_dir or '/'
        else:
            resolved_base = ensure_remote_base(ftp, base_dir)
            print(f"Remote-Basis: {resolved_base}")
        for rel_path in files:
            target_base, target_rel = adjust_upload_target(resolved_base, rel_path)
            upload_file(ftp, target_base, rel_path, dry_run=args.dry_run, remote_rel=target_rel)
    finally:
        if ftp and not args.dry_run:
            ftp.quit()


if __name__ == '__main__':
    try:
        main()
    except subprocess.CalledProcessError as exc:
        print(exc)
        sys.exit(exc.returncode)
    except Exception as err:
        print(f"✗ Deployment fehlgeschlagen: {err}")
        sys.exit(1)
