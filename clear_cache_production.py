#!/usr/bin/env python3
"""
SSH script to clear Laravel cache on production server
"""
import pexpect
import sys

# Server credentials
HOST = "152.53.86.223"
USER = "citycomm"
PASSWORD = "Alphia@2025"
PROJECT_DIR = "/home/citycomm/soud-laravel"

def run_command():
    child = pexpect.spawn(f'ssh {USER}@{HOST}')

    # Wait for password prompt
    child.expect('password:')
    child.sendline(PASSWORD)

    # Wait for shell prompt
    child.expect('$')

    # Change to project directory
    child.sendline(f'cd {PROJECT_DIR}')
    child.expect('$')

    # Run cache clear
    child.sendline('php artisan optimize:clear')
    child.expect('$', timeout=60)

    # Output result
    print(child.before.decode('utf-8', errors='ignore'))

    # Exit
    child.sendline('exit')
    child.wait()

if __name__ == "__main__":
    try:
        run_command()
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)
