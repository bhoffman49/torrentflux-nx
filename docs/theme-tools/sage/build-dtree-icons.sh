#!/bin/bash
# Build the file-tree icons for the torrent details popup (dtree.js, images/dtree/*.gif) in the
# sage theme palette: Material Icons (Round, Apache-2.0) for the root, folders and files,
# and dotted tree lines with boxed +/- drawn to match. PNG data under the old .gif names.
# Usage: build-dtree-icons.sh <svg cache dir> <outdir> <current dtree dir>
set -e
SRC=$1; OUT=$2; CUR=$3
RAW=https://cdn.jsdelivr.net/npm/@material-design-icons/svg@0.14.13/round
mkdir -p "$SRC" "$OUT"
rm -f "$OUT"/*

ACCENT='#2f5f5f'; FOLDER='#8a6a1e'; TEXT='#3b4a4a'; DIM='#7c857f'; BG='#bbc2bb'

# root, folders and files: 16px glyph centred on the 18px (root 19px) canvas
icon() {  # material-icon colour width outfile
  [ -s "$SRC/$1.svg" ] || curl -sfL -o "$SRC/$1.svg" "$RAW/$1.svg"
  sed "s/<svg /<svg fill=\"$2\" /" "$SRC/$1.svg" > /tmp/dt-$$.svg
  rsvg-convert -w 16 -h 16 /tmp/dt-$$.svg -o /tmp/dt-$$.png
  convert -background none /tmp/dt-$$.png -gravity center -extent ${3}x18 "PNG32:$OUT/$4"
}
icon file_download    "$ACCENT" 19 base.gif
icon folder           "$FOLDER" 18 folder.gif
icon folder_open      "$FOLDER" 18 folderopen.gif
icon insert_drive_file "$TEXT"  18 page.gif
rm -f /tmp/dt-$$.svg /tmp/dt-$$.png

# tree lines: dotted, centre column x=8, centre row y=8
dots() {  # x0 y0 x1 y1 (a straight run, every other pixel)
  local s="" x y
  if [ "$1" = "$3" ]; then for ((y=$2; y<=$4; y+=2)); do s+="point $1,$y "; done
  else for ((x=$1; x<=$3; x+=2)); do s+="point $x,$2 "; done; fi
  echo "$s"
}
box() {  # sign: plus|minus
  local d="fill $BG stroke $DIM rectangle 4,4 12,12 stroke $TEXT line 6,8 10,8"
  [ "$1" = plus ] && d+=" line 8,6 8,10"
  echo "$d"
}
draw() {  # outfile draw-commands...
  local f=$1; shift
  convert -size 18x18 xc:none -fill "$DIM" -stroke none -draw "$*" "PNG32:$OUT/$f"
}
V="$(dots 8 0 8 17)"; VTOP="$(dots 8 0 8 8)"; H="$(dots 8 8 17 8)"; HBOX="$(dots 14 8 17 8)"
convert -size 18x18 xc:none "PNG32:$OUT/empty.gif"
draw line.gif          "$V"
draw join.gif          "$V $H"
draw joinbottom.gif    "$VTOP $H"
draw plus.gif          "$V $HBOX $(box plus)"
draw plusbottom.gif    "$VTOP $HBOX $(box plus)"
draw minus.gif         "$V $HBOX $(box minus)"
draw minusbottom.gif   "$VTOP $HBOX $(box minus)"
draw nolines_plus.gif  "$(box plus)"
draw nolines_minus.gif "$(box minus)"

# sample sheet: old | new, then new at 3x
cd "$OUT"
args=()
for f in $(ls); do
  convert -background "$BG" "$CUR/$f" -flatten -gravity center -extent 24x24 /tmp/dt-old-$f.png
  convert -background "$BG" "$f" -flatten -gravity center -extent 24x24 /tmp/dt-new1-$f.png
  convert -background "$BG" "$f" -flatten -filter point -resize 300% -gravity center -extent 60x60 /tmp/dt-new3-$f.png
  convert /tmp/dt-old-$f.png /tmp/dt-new1-$f.png +append -gravity center -background "$BG" -extent 60x60 /tmp/dt-pair-$f.png
  convert /tmp/dt-pair-$f.png /tmp/dt-new3-$f.png +append -set label "${f%.gif}" /tmp/dt-cell-$f.png
  args+=(/tmp/dt-cell-$f.png)
done
montage -background "$BG" -fill "$TEXT" -pointsize 11 -label '%l' "${args[@]}" -tile 5x -geometry +8+4 /tmp/dtree-sample.png
rm -f /tmp/dt-old-* /tmp/dt-new1-* /tmp/dt-new3-* /tmp/dt-pair-* /tmp/dt-cell-*
ls "$OUT" | wc -l
