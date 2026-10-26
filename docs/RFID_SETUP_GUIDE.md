# RFID Setup Guide

## What "RFID support" means in this delivery

The system treats an RFID tag number as a **plain text string**
(`rfid_tags.tag_number`, unique while active). It does not talk to any
RFID reader hardware over USB, serial, or Bluetooth directly. Instead
it relies on a specific, very common class of hardware:

### Keyboard-wedge (HID) USB RFID readers

The vast majority of inexpensive USB RFID readers (125 kHz EM4100-style
livestock ear-tag readers included) operate in **keyboard-wedge mode**:
to the computer, the reader looks exactly like a USB keyboard. When you
scan a tag, the reader "types" the tag's number followed by Enter,
wherever the text cursor currently is, and does nothing else.

This means:

- **No driver is required.** Windows recognizes it as a standard HID
  keyboard.
- **No app-side integration code is required**, because any focused
  text input field receives the scan exactly as if someone had typed
  the number and pressed Enter.
- The RFID scan page (`/admin/livestock/rfid-scan`, built in
  `resources/views/admin/livestock/rfid_scan.php`) is simply a page
  with one text input, auto-focused, that submits the form on Enter.
  Click into the field, scan a tag, and the page looks up the animal
  by `LivestockService::resolveTag()` and shows its record.

### How to test a keyboard-wedge reader with this system

1. Plug the USB reader into the computer running the browser. (It does
   not need to be the same machine as the web/database server -- any
   machine that can reach the app in a browser works.)
2. Open any text editor (even Notepad) and scan a tag. Confirm the tag
   number appears followed by a newline. If nothing happens, the reader
   is not in keyboard-wedge mode -- check its manual; some readers have
   a physical DIP switch or a configuration tag to scan that switches
   output mode.
3. Sign in to the staff portal and go to **Livestock → Scan Tag**
   (`/livestock-rfid/scan`).
4. Click into the input box (it is auto-focused on page load, but click
   it to be sure) and scan the tag again.
5. The page should show the matching animal's record, or an explicit
   "No active animal is linked to this tag" message if the tag is not
   yet assigned -- `resolveTag()` always writes an audit log entry for
   the scan attempt either way (see `src/Services/LivestockService.php`,
   `resolveTag()`), so every scan -- successful or not -- is traceable
   later.
6. To assign a new tag to an animal for the first time, open the
   animal's record and use **Assign RFID Tag**, scanning into that
   field the same way.

### Manual entry works identically

Everything above also works if staff simply type the tag number by
hand instead of scanning -- the application has no way to distinguish
"typed" from "scanned" input, by design, because a keyboard-wedge
reader's output is indistinguishable from typing. This is intentional
and documented in `LivestockService`'s class docblock.

## What is explicitly NOT built: a serial/native reader bridge

Some RFID readers (particularly panel/fixed readers used at a barn
entrance, or readers that speak a vendor protocol over RS-232/RS-485
rather than acting as a keyboard) require a **background service** that
opens the serial port, decodes the reader's byte protocol, and pushes
the resulting tag number into the web page (typically over a local
WebSocket).

**This was not built in this delivery.** No serial-port bridge service,
no vendor SDK integration, and no WebSocket push service exists in this
codebase. If your hardware is this type, it will not work with this
application as delivered. Building one requires:

1. Knowing the exact reader model and its documented serial protocol
   (often vendor-specific and NOT self-describing -- it cannot be
   guessed or implemented without the manufacturer's protocol
   document).
2. A small daemon (PHP, Node, or Python) that holds the serial port
   open and exposes tag reads to the browser, e.g. via a local
   WebSocket or Server-Sent Events endpoint that the scan page listens
   to.

This is called out as a known limitation in
`docs/FINAL_ACCEPTANCE_REPORT.md`. If you have a specific reader model,
share its protocol documentation and a bridge can be built as a
follow-up -- it should not be assumed possible without that
documentation, and no such integration should be treated as already
present in this codebase.

## Tag lifecycle rules enforced by the application

- A tag can be active on at most one animal at a time
  (`rfid_tags.active_tag_number` generated column + unique index --
  see `docs/DATABASE_PORTABILITY.md`). Attempting to assign a tag
  that is already active elsewhere is rejected with a clear error,
  not silently overwritten.
- Deactivating a tag (lost tag, animal disposed/sold) keeps the
  historical row -- tags are never deleted, only marked inactive --
  so the full chain of custody for a tag number remains in the
  animal's history even after reassignment to a new animal.
