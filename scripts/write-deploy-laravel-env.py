#!/usr/bin/env python3
"""
Build laravel/.env from .env.example for CI/CD.
Reads replacement values from the environment (set by GitHub Actions from secrets).
"""
from __future__ import annotations

import os
import re
import sys
from pathlib import Path


def quote_env_value(value: str) -> str:
    if value == "":
        return ""
    if re.search(r'[\s#"\'\\]', value):
        escaped = value.replace("\\", "\\\\").replace('"', '\\"')
        return f'"{escaped}"'
    return value


def main() -> int:
    root = Path(__file__).resolve().parent.parent
    laravel = root / "laravel"
    example = laravel / ".env.example"
    out_path = laravel / ".env"
    if not example.is_file():
        print("Missing laravel/.env.example", file=sys.stderr)
        return 1

    override_keys = {
        "APP_KEY",
        "APP_URL",
        "APP_ENV",
        "APP_DEBUG",
        "ADMIN_EMAIL",
        "ADMIN_PASSWORD",
        "DB_HOST",
        "DB_PORT",
        "DB_DATABASE",
        "DB_USERNAME",
        "DB_PASSWORD",
        "TOKEN_MIGRATION",
        "TOKEN_CRON",
    }

    env_from_os = {k: os.environ.get(k) for k in override_keys}

    lines = example.read_text(encoding="utf-8").splitlines()
    out_lines: list[str] = []
    seen_override: set[str] = set()

    for line in lines:
        stripped = line.strip()
        if not stripped or stripped.startswith("#") or "=" not in line:
            out_lines.append(line)
            continue
        key, _, _rest = line.partition("=")
        key = key.strip()
        if key in env_from_os and env_from_os[key] is not None and env_from_os[key] != "":
            val = quote_env_value(env_from_os[key] or "")
            out_lines.append(f"{key}={val}")
            seen_override.add(key)
        else:
            out_lines.append(line)

    for key in sorted(override_keys):
        if key in seen_override:
            continue
        val = env_from_os.get(key)
        if val is None or val == "":
            continue
        out_lines.append(f"{key}={quote_env_value(val)}")

    out_path.write_text("\n".join(out_lines) + "\n", encoding="utf-8")
    print(f"Wrote {out_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
