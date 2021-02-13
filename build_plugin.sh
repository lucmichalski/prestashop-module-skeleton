#!/usr/bin/env bash

# Script to build PrestaShop module for specific versions.

# if any variable is unset exits with error
set -u

# exists at first non 0 exit code
# set -e

# verbose commands executed
set -x

# Usage info
show_help() {
cat << EOF
Usage: ${0##*/} [-h] [-v VER] [-o OUT_DIR]...
Build Evolutive-Manticore PrestaShop plugin

    -h           display this help and exit
    -o OUT_DIR   output directory where store the zip file will be built, cur_dir by default

EOF
}

# A POSIX variable
OPTIND=1         # Reset in case getopts has been used previously in the shell.

tmp_dir=$(mktemp -d)
script_dir="$( cd "$(dirname "$0")" ; pwd -P )"
prev_dir=$(pwd)
out_dir=${prev_dir}

while getopts "h?v:o:" opt; do
    case "$opt" in
        h|\?)
            show_help
            exit 0
            ;;
        o)  out_dir=$OPTARG
            ;;
    esac
done

command -v zip >/dev/null 2>&1 || { echo >&2 "You need the zip binary in the path"; exit 1; }

cd "${script_dir}"
pwd

# Shared files
cp -a manticore "${tmp_dir}"
out_fname="${out_dir}/manticore.zip"
# Compress results
cd "${tmp_dir}"
ls -l 
zip -u -r --exclude=*.DS_Store* "$out_fname" manticore > /dev/null
pwd
cd "${prev_dir}"

# Remove temp directory
rm -R "${tmp_dir}"

echo "Module built in ${out_fname}"
