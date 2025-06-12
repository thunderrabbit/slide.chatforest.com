#!/bin/bash

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 file1.ext [file2.ext ...]"
    exit 1
fi

for file in "$@"; do
    # Extract the file extension without the leading dot
    ext="${file##*.}"

    echo -e "Here is the source for $file:\n"
    echo '```'$ext
    cat "$file"
    echo '```'
    echo -e "\n------------------------\n"
done
