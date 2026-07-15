import os

possible_paths = [
    r"C:\xampp\php\php.exe",
    r"C:\wamp64\bin\php\php.exe",
    r"C:\wamp\bin\php\php.exe",
    r"C:\laragon\bin\php\php.exe",
    r"C:\Program Files\PHP\php.exe",
    r"C:\Program Files (x86)\PHP\php.exe",
]

found = False
for path in possible_paths:
    if os.path.exists(path):
        print("FOUND PHP AT:", path)
        found = True

if not found:
    print("PHP not found in standard paths.")
