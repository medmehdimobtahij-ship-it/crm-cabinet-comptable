"""Ouvre Claude Desktop, colle le prompt d'un fichier dans une nouvelle conversation et l'envoie (outil local, Windows).

Lancé par le CRM local (php -S sur ce PC) pour « Compléter avec Claude » : Claude lit Pennylane avec son connecteur et écrit le
résultat avec l'extension Filesystem ; le CRM surveille ce fichier. Rien d'autre : pas de clic, pas de touche Alt.
    pythonw outils\\claude_desktop.py <fichier du prompt>
"""
import ctypes
import os
import sys
import time
from ctypes import wintypes

user32, kernel32 = ctypes.windll.user32, ctypes.windll.kernel32


def fenetre_claude():
    """Fenêtre visible de Claude Desktop (application du Microsoft Store), ou None."""
    trouve = []

    @ctypes.WINFUNCTYPE(wintypes.BOOL, wintypes.HWND, wintypes.LPARAM)
    def visite(hwnd, _):
        if not user32.IsWindowVisible(hwnd) or user32.GetWindowTextLengthW(hwnd) == 0:
            return True
        pid = wintypes.DWORD()
        user32.GetWindowThreadProcessId(hwnd, ctypes.byref(pid))
        h = kernel32.OpenProcess(0x1000, False, pid.value)  # PROCESS_QUERY_LIMITED_INFORMATION
        if h:
            buf, n = ctypes.create_unicode_buffer(1024), wintypes.DWORD(1024)
            if kernel32.QueryFullProcessImageNameW(h, 0, buf, ctypes.byref(n)):
                chemin = buf.value.lower()
                if "windowsapps" in chemin and "claude" in chemin:
                    trouve.append(hwnd)
            kernel32.CloseHandle(h)
        return not trouve

    user32.EnumWindows(visite, 0)
    return trouve[0] if trouve else None


def _touche(vk, relache=False):
    user32.keybd_event(vk, 0, 2 if relache else 0, 0)


def presse_papiers(texte):
    kernel32.GlobalAlloc.restype = ctypes.c_void_p
    kernel32.GlobalLock.restype = ctypes.c_void_p
    kernel32.GlobalLock.argtypes = [ctypes.c_void_p]
    user32.SetClipboardData.argtypes = [ctypes.c_uint, ctypes.c_void_p]
    octets = (texte + "\0").encode("utf-16-le")
    for _ in range(20):
        if user32.OpenClipboard(None):
            break
        time.sleep(0.1)
    try:
        user32.EmptyClipboard()
        h = kernel32.GlobalAlloc(0x0002, len(octets))
        ctypes.memmove(kernel32.GlobalLock(h), octets, len(octets))
        kernel32.GlobalUnlock(ctypes.c_void_p(h))
        user32.SetClipboardData(13, h)  # CF_UNICODETEXT
    finally:
        user32.CloseClipboard()


def main():
    texte = open(sys.argv[1], encoding="utf-8").read().replace("\r\n", "\n").replace("\n", "\r\n")
    presse_papiers(texte)
    os.startfile("claude://claude.ai/new")
    debut, hwnd = time.time(), None
    while not hwnd and time.time() - debut < 30:
        time.sleep(0.5)
        hwnd = fenetre_claude()
    if not hwnd:
        sys.exit(1)
    time.sleep(4)  # la nouvelle conversation s'ouvre
    user32.ShowWindow(hwnd, 9)
    user32.SetForegroundWindow(hwnd)
    time.sleep(0.7)
    _touche(0x11); _touche(0x56); _touche(0x56, True); _touche(0x11, True)  # Ctrl+V
    time.sleep(0.8)
    _touche(0x0D); _touche(0x0D, True)  # Entrée


if __name__ == "__main__":
    main()
