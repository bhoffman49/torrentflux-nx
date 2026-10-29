#!/bin/bash
# Build flat Material Icons (Round style, Apache-2.0) file-type icons for TorrentFlux
# html/images/files/<ext>.png (dir.php picks them by lower-case extension; "unknown" = no extension),
# coloured by category with the sage theme palette, plus a sample sheet.
# Usage: build-file-icons.sh <svg cache dir> <outdir> <current files dir>
set -e
SRC=$1; OUT=$2; CUR=$3
RAW=https://cdn.jsdelivr.net/npm/@material-design-icons/svg@0.14.13/round
mkdir -p "$SRC" "$OUT"
rm -f "$OUT"/*

BLUE='#2f5f5f'; GREEN='#3f7a3f'; RED='#a33a2c'; AMBER='#8a6a1e'; VIOLET='#5d4f7a'
TEXT='#3b4a4a'; DIM='#58635d'

# material-icon  colour  extensions...
MAP="
video_file      $BLUE   avi mov mpeg mpg ogm rm vob wmv mkv mp4 m4v webm flv ts m2ts divx xvid 3gp
audio_file      $GREEN  ac3 mp3 ogg wav wma midi mid flac m4a aac opus ape dts
queue_music     $GREEN  cue m3u m3u8 pls
image           $AMBER  bmp gif jpg jpeg png webp tif tiff svg ico
album           $VIOLET iso img bin nrg mdf mds dmg
folder_zip      $TEXT   cab gz rar tar zip 7z bz2 xz tgz zst lz lzma
inventory_2     $TEXT   rpm deb jar apk msi
picture_as_pdf  $RED    pdf
description     $TEXT   doc docx odt rtf
table_chart     $GREEN  xls xlsx ods csv
slideshow       $AMBER  ppt pptx odp
article         $TEXT   txt nfo md diz
receipt_long    $DIM    log
menu_book       $VIOLET man epub mobi azw3 cbz cbr
subtitles       $TEXT   srt sub ass ssa idx vtt
code            $DIM    c h java php py js css cpp
html            $BLUE   html htm
terminal        $DIM    sh exe bat cmd
verified        $GREEN  sfv md5 sha1 sha256 sha512 sha384 sha224 crc b2 sums par2
key             $AMBER  asc sig gpg pgp pub sign
file_download   $BLUE   torrent nzb
insert_drive_file $DIM  unknown
"

echo "$MAP" | while read -r icon colour exts; do
  [ -z "$icon" ] && continue
  [ -s "$SRC/$icon.svg" ] || curl -sfL -o "$SRC/$icon.svg" "$RAW/$icon.svg"
  sed "s/<svg /<svg fill=\"$colour\" /" "$SRC/$icon.svg" > /tmp/fi-$$.svg
  rsvg-convert -w 48 -h 48 /tmp/fi-$$.svg -o /tmp/fi-$$.png
  for e in $exts; do convert /tmp/fi-$$.png "PNG32:$OUT/$e.png"; done
done
rm -f /tmp/fi-$$.svg /tmp/fi-$$.png

# sample sheet: old (if any) | new, then new at 3x
cd "$OUT"
args=()
for f in $(ls); do
  if [ -f "$CUR/$f" ]; then convert -background "#bbc2bb" "$CUR/$f" -flatten -gravity center -extent 24x24 /tmp/fi-old-$f.png
  else convert -size 24x24 xc:"#bbc2bb" /tmp/fi-old-$f.png; fi
  convert -background "#bbc2bb" "$f" -flatten -resize 16x16 -gravity center -extent 24x24 /tmp/fi-new1-$f.png
  convert -background "#bbc2bb" "$f" -flatten -resize 48x48 -gravity center -extent 56x56 /tmp/fi-new3-$f.png
  convert /tmp/fi-old-$f.png /tmp/fi-new1-$f.png +append -gravity center -background "#bbc2bb" -extent 56x56 /tmp/fi-pair-$f.png
  convert /tmp/fi-pair-$f.png /tmp/fi-new3-$f.png +append -set label "${f%.png}" /tmp/fi-cell-$f.png
  args+=(/tmp/fi-cell-$f.png)
done
montage -background "#bbc2bb" -fill "#000000" -pointsize 11 -label '%l' "${args[@]}" -tile 10x -geometry +8+4 /tmp/files-sample.png
rm -f /tmp/fi-old-* /tmp/fi-new1-* /tmp/fi-new3-* /tmp/fi-pair-* /tmp/fi-cell-*
ls "$OUT" | wc -l
