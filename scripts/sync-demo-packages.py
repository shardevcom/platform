#!/usr/bin/env python3
"""Sync official packages into the demo app, renaming the vendor namespace.

AGENTS.md requires demo/packages/<user> to be a copy of packages/<official>,
but the demo installs them under the `shardev` vendor while the framework
ships `shardevcom`. Copying verbatim breaks composer autoloading and package
discovery, so the manifest is rewritten as part of the sync.

User packages living directly in demo/packages/<user>/ are never touched.
"""

import collections
import json
import pathlib
import shutil
import sys

SOURCE_VENDOR = 'shardevcom'
TARGET_VENDOR = 'shardev'


def sync(src_root: pathlib.Path, dst_root: pathlib.Path) -> list[str]:
    synced = []

    for pkg in sorted(p.name for p in src_root.iterdir() if p.is_dir()):
        dst = dst_root / pkg

        if dst.exists():
            shutil.rmtree(dst)

        shutil.copytree(src_root / pkg, dst)
        rewrite_manifest(dst / 'composer.json', pkg)
        synced.append(pkg)

    return synced


def rewrite_manifest(manifest: pathlib.Path, pkg: str) -> None:
    data = json.loads(manifest.read_text(), object_pairs_hook=collections.OrderedDict)
    data['name'] = f'{TARGET_VENDOR}/{pkg}'

    if 'require' in data:
        required = collections.OrderedDict()
        for name, version in data['require'].items():
            if name.startswith(SOURCE_VENDOR + '/'):
                name = f'{TARGET_VENDOR}/{name.split("/", 1)[1]}'
            required[name] = version
        data['require'] = required

    manifest.write_text(json.dumps(data, indent=4) + '\n')


def main() -> int:
    root = pathlib.Path(__file__).resolve().parent.parent
    src_root = root / 'packages' / SOURCE_VENDOR
    dst_root = root / 'demo' / 'packages' / TARGET_VENDOR

    for pkg in sync(src_root, dst_root):
        print(f'synced {pkg}')

    return 0


if __name__ == '__main__':
    sys.exit(main())