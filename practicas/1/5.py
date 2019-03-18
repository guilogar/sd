import sys

name = str(sys.argv[1])
with open("etc/passwd") as archivo:
    for linea in archivo:
        if name in linea:
            cadena = linea

print(cadena.split('/')[4])
