#!/bin/bash
# Build flat Material Icons (Outlined style, Apache-2.0) replacements for TorrentFlux html/images/*,
# coloured with the walnut theme palette (bronze line icons), plus a before/after sample sheet.
# Usage: build-flat-icons.sh <svg cache dir> <outdir> <current images dir>
set -e
SRC=$1; OUT=$2; CUR=$3
RAW=https://cdn.jsdelivr.net/npm/@material-design-icons/svg@0.14.13/outlined
mkdir -p "$SRC" "$OUT"
rm -f "$OUT"/*

BLUE='#c9a46b'; GREEN='#8fae7a'; RED='#c97a64'; AMBER='#d8b56a'
TEXT='#d9d2c7'; DIM='#9a928a'; OFF='#5a544e'

# target-file  WxH  material-icon  colour  [glyph px, default = smaller side]
MAP="
admin_user.gif     48x48 admin_panel_settings $BLUE
superadmin.gif     48x48 admin_panel_settings $AMBER
user.gif           48x48 person               $TEXT
user.png           24x24 person               $TEXT
user_offline.gif   48x48 person_outline       $OFF
user_offline.png   24x24 person_outline       $OFF
user_group.gif     48x48 group                $TEXT
all.gif            16x16 public               $BLUE
who.gif            16x16 query_stats          $BLUE
hdd.gif            16x16 storage              $TEXT
make.gif           48x48 note_add             $BLUE
make.png           24x24 note_add             $BLUE
properties.png     48x48 description          $TEXT
info.gif           16x18 info                 $BLUE  16
error.gif          16x16 error                $RED
download.gif       16x16 file_download        $BLUE
download_owner.gif 48x48 file_download        $BLUE
down.gif           48x48   file_download        $BLUE
arrow.gif          9x9   chevron_right        $BLUE
queued.gif         48x48 schedule             $AMBER
time.gif           48x48 schedule             $DIM
locked.gif         16x16 lock                 $DIM
view_nfo.gif       48x48 article              $TEXT
up_dir.gif         48x48 drive_folder_upload  $BLUE
uparrow.png        48x48 arrow_upward         $TEXT
downarrow.png      48x48 arrow_downward       $TEXT
folder.gif         16x16 folder               $BLUE
folder.png         24x24 folder               $BLUE
folder2.gif        48x48 folder               $BLUE
edit.png           48x48 edit                 $BLUE
delete_on.gif      48x48 delete               $RED
delete_off.gif     48x48 delete               $OFF
delete.png         48x48 delete               $RED
kill.gif           48x48 stop_circle          $RED
run_on.gif         48x48 play_circle          $GREEN
run_off.gif        48x48 play_circle          $OFF
seed_on.gif        48x48 file_upload          $GREEN
logout.gif         13x12 logout               $DIM
reply.gif          48x48 reply                $BLUE
new_message.gif    48x48 mail                 $BLUE
old_message.gif    48x48 drafts               $DIM
tar_down.gif       16x16 archive              $TEXT
tar_down.png       48x48 archive              $TEXT
green.gif          48x48 circle               $GREEN 30
red.gif            48x48 circle               $RED   30
yellow.gif         48x48 circle               $AMBER 30
black.gif          48x48 circle               $OFF   30
"

echo "$MAP" | while read -r file size icon colour px; do
  [ -z "$file" ] && continue
  [ -s "$SRC/$icon.svg" ] || curl -sfL -o "$SRC/$icon.svg" "$RAW/$icon.svg"
  W=${size%x*}; H=${size#*x}; S=${px:-$(( W < H ? W : H ))}
  sed "s/<svg /<svg fill=\"$colour\" /" "$SRC/$icon.svg" > /tmp/fl-$$.svg
  rsvg-convert -w $S -h $S /tmp/fl-$$.svg -o /tmp/fl-$$.png
  # always PNG data (full alpha), even for .gif names - browsers sniff the format
  convert -background none /tmp/fl-$$.png -gravity center -extent ${W}x${H} "PNG32:$OUT/$file"
done
rm -f /tmp/fl-$$.svg /tmp/fl-$$.png

# sample sheet: old | new at 1x, then new at 3x, on the theme panel colour
cd "$OUT"
args=()
for f in $(ls); do
  convert -background "#2a2725" "$CUR/$f[0]" -flatten -resize 16x16\> -gravity center -extent 24x24 /tmp/fl-old-$f.png 2>/dev/null || convert -size 24x24 xc:"#2a2725" /tmp/fl-old-$f.png
  convert -background "#2a2725" "$f" -flatten -gravity center -extent 24x24 /tmp/fl-new1-$f.png
  convert -background "#2a2725" "$f" -flatten -filter point -resize 300% -gravity center -extent 72x72 /tmp/fl-new3-$f.png
  convert /tmp/fl-old-$f.png /tmp/fl-new1-$f.png +append -gravity center -background "#2a2725" -extent 72x72 /tmp/fl-pair-$f.png
  convert /tmp/fl-pair-$f.png /tmp/fl-new3-$f.png +append -set label "$f" /tmp/fl-cell-$f.png
  args+=(/tmp/fl-cell-$f.png)
done
montage -background "#2a2725" -fill "#d9d2c7" -pointsize 11 -label '%l' "${args[@]}" -tile 6x -geometry +10+6 /tmp/flat-sample.png
rm -f /tmp/fl-old-* /tmp/fl-new1-* /tmp/fl-new3-* /tmp/fl-pair-* /tmp/fl-cell-*
ls "$OUT" | wc -l
