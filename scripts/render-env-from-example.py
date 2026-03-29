#!/usr/bin/env python3
import os
import re
import sys
from pathlib import Path

KEY_RE = re.compile(r'^([A-Za-z_][A-Za-z0-9_]*)=(.*)$')

def escape_env_value(value: str) -> str:
    if value == "":
        return ""

    # Ak je v hodnote medzera, #, úvodzovky alebo newline, daj ju do dvojitých úvodzoviek
    needs_quotes = any(ch in value for ch in [' ', '#', '"', '\n', '\r', '\t'])
    if not needs_quotes:
        return value

    value = value.replace("\\", "\\\\").replace('"', '\\"')
    value = value.replace("\n", "\\n").replace("\r", "\\r")
    return f'"{value}"'

def render_env(example_path: Path, output_path: Path):
    lines = example_path.read_text(encoding="utf-8").splitlines()
    rendered = []

    for line in lines:
        stripped = line.strip()

        if not stripped or stripped.startswith("#"):
            rendered.append(line)
            continue

        match = KEY_RE.match(line)
        if not match:
            rendered.append(line)
            continue

        key, default_value = match.groups()
        env_value = os.getenv(key)

        if env_value is None:
            rendered.append(line)
        else:
            rendered.append(f"{key}={escape_env_value(env_value)}")

    output_path.write_text("\n".join(rendered) + "\n", encoding="utf-8")

def main():
    if len(sys.argv) != 3:
        print("Usage: render-env-from-example.py <input.env.example> <output.env>")
        sys.exit(1)

    example_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])

    if not example_path.exists():
        print(f"Input file not found: {example_path}")
        sys.exit(1)

    output_path.parent.mkdir(parents=True, exist_ok=True)
    render_env(example_path, output_path)
    print(f"Generated {output_path} from {example_path}")

if __name__ == "__main__":
    main()