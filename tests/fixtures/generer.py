#!/usr/bin/env python3
"""Régénère les fixtures textuelles DXF/CSV de l'étape 04 : python3 tests/fixtures/generer.py"""
import os

ICI = os.path.dirname(os.path.abspath(__file__))


def g(code, val):
    return f"{code}\n{val}\n"


def en_tete(insunits=None):
    s = g(0, 'SECTION') + g(2, 'HEADER') + g(9, '$ACADVER') + g(1, 'AC1015')
    if insunits is not None:
        s += g(9, '$INSUNITS') + g(70, insunits)
    return s + g(0, 'ENDSEC')


def section_blocs(blocs):
    s = g(0, 'SECTION') + g(2, 'BLOCKS')
    for nom, ents in blocs.items():
        s += g(0, 'BLOCK') + g(8, '0') + g(2, nom) + g(70, 0) + g(10, 0) + g(20, 0) + g(30, 0) + ents + g(0, 'ENDBLK') + g(8, '0')
    return s + g(0, 'ENDSEC')


def entites(contenu):
    return g(0, 'SECTION') + g(2, 'ENTITIES') + contenu + g(0, 'ENDSEC')


def fin():
    return g(0, 'EOF')


def lwpoly(points, calque='A-WALL', ferme=True):
    s = g(0, 'LWPOLYLINE') + g(8, calque) + g(90, len(points)) + g(70, 1 if ferme else 0)
    for x, y in points:
        s += g(10, x) + g(20, y)
    return s


def polyline(points, calque='A-WALL'):
    s = g(0, 'POLYLINE') + g(8, calque) + g(66, 1) + g(70, 1)
    for x, y in points:
        s += g(0, 'VERTEX') + g(8, calque) + g(10, x) + g(20, y) + g(30, 0)
    return s + g(0, 'SEQEND') + g(8, calque)


def line(a, b, calque='A-WALL'):
    return g(0, 'LINE') + g(8, calque) + g(10, a[0]) + g(20, a[1]) + g(30, 0) + g(11, b[0]) + g(21, b[1]) + g(31, 0)


def arc(c, r, a1, a2, calque='A-WALL'):
    return g(0, 'ARC') + g(8, calque) + g(10, c[0]) + g(20, c[1]) + g(40, r) + g(50, a1) + g(51, a2)


def ecrire(chemin, texte, mode='w', enc='utf-8'):
    with open(os.path.join(ICI, chemin), mode, encoding=None if 'b' in mode else enc, newline='\n') as f:
        f.write(texte)


def dxf(contenu, insunits=4, blocs=None):
    return en_tete(insunits) + (section_blocs(blocs) if blocs else '') + entites(contenu) + fin()


RECT = [(0, 0), (4000, 0), (4000, 3000), (0, 3000)]
L = [(0, 0), (5000, 0), (5000, 2000), (2000, 2000), (2000, 4000), (0, 4000)]
PAN = [(0, 0), (4000, 0), (4000, 2000), (3000, 3000), (0, 3000)]


def decale(pts, dx, dy):
    return [(x + dx, y + dy) for x, y in pts]


def lignes(pts, jeu=0, calque='A-WALL'):
    """Segments non chaînés exactement : chaque ligne s'arrête `jeu` mm avant le sommet suivant."""
    out = ''
    n = len(pts)
    for i in range(n):
        a = pts[i]
        b = pts[(i + 1) % n]
        dx, dy = b[0] - a[0], b[1] - a[1]
        d = (dx * dx + dy * dy) ** 0.5
        ux, uy = dx / d, dy / d
        a2 = (a[0] + ux * jeu / 2, a[1] + uy * jeu / 2)
        b2 = (b[0] - ux * jeu / 2, b[1] - uy * jeu / 2)
        out += line((round(a2[0], 3), round(a2[1], 3)), (round(b2[0], 3), round(b2[1], 3)), calque)
    return out


# DXF
ecrire('dxf/rectangle.dxf', dxf(lwpoly(decale(RECT, 152000, 6812000))))
ecrire('dxf/l.dxf', dxf(polyline(L)))
ecrire('dxf/pan_coupe.dxf', dxf(lignes(PAN)))
ecrire('dxf/lignes_jeux_2mm.dxf', dxf(lignes(L, 2)))
ecrire('dxf/lignes_jeux_8mm.dxf', dxf(lignes(RECT, 8)))
ecrire('dxf/avec_arc.dxf', dxf(
    line((0, 0), (4000, 0)) + line((4000, 0), (4000, 3000)) + line((4000, 3000), (2000, 3000))
    + arc((1000, 3000), 1000, 0, 180) + line((0, 3000), (0, 0))
))
ecrire('dxf/arc_isole.dxf', dxf(lwpoly(RECT) + arc((6000, 1000), 500, 0, 180, 'A-DECOR')))
ecrire('dxf/cercle_isole.dxf', dxf(lwpoly(RECT) + g(0, 'CIRCLE') + g(8, 'A-DECOR') + g(10, 6000) + g(20, 1000) + g(40, 400)))
bulge = g(0, 'LWPOLYLINE') + g(8, 'A-WALL') + g(90, 4) + g(70, 1) + g(10, 0) + g(20, 0) + g(10, 4000) + g(20, 0) + g(42, 0.5) + g(10, 4000) + g(20, 3000) + g(10, 0) + g(20, 3000)
ecrire('dxf/avec_bulge.dxf', dxf(bulge))
ecrire('dxf/binaire.dxf', 'AutoCAD Binary DXF\r\n\x1a\x00' + 'SECTION\x00' * 20, 'w', 'latin-1')
corrompu = en_tete(4) + g(0, 'SECTION') + g(2, 'ENTITIES') + g(0, 'LWPOLYLINE') + g(8, 'A-WALL') + g(10, 0) + '20\n'
ecrire('dxf/corrompu.dxf', corrompu)
ecrire('dxf/sans_fin.dxf', en_tete(4) + g(0, 'SECTION') + g(2, 'ENTITIES') + lwpoly(RECT))

porte = line((0, 0), (900, 0)) + line((0, 0), (0, 900))
fenetre = line((0, 0), (1200, 0)) + line((0, 100), (1200, 100))
attr = lambda tag, val: g(0, 'ATTRIB') + g(8, 'A-DOOR') + g(10, 0) + g(20, 0) + g(40, 50) + g(1, val) + g(2, tag) + g(70, 0)
ins_porte = g(0, 'INSERT') + g(66, 1) + g(8, 'A-DOOR') + g(2, 'PORTE') + g(10, 500) + g(20, 0) + g(41, 1) + g(42, 1) + g(50, 0) + attr('WIDTH', 900) + attr('HEIGHT', 2040) + g(0, 'SEQEND')
ins_fen = g(0, 'INSERT') + g(8, 'A-WINDOW') + g(2, 'FENETRE') + g(10, 1500) + g(20, 3000) + g(41, 1) + g(42, 1) + g(50, 0)
ins_equip = g(0, 'INSERT') + g(8, 'A-FURN') + g(2, 'RADIATEUR') + g(10, 3000) + g(20, 1000) + g(41, 1) + g(42, 1) + g(50, 0)
ecrire('dxf/portes_fenetres.dxf', dxf(
    lwpoly(RECT) + ins_porte + ins_fen + ins_equip + g(0, 'TEXT') + g(8, 'A-ANNO') + g(10, 100) + g(20, 100) + g(40, 100) + g(1, 'Salon'),
    blocs={'PORTE': porte, 'FENETRE': fenetre, 'RADIATEUR': line((0, 0), (600, 0))},
))
ecrire('dxf/unites_metres.dxf', dxf(lwpoly([(x / 1000, y / 1000) for x, y in RECT]), insunits=6))
ecrire('dxf/unites_cm.dxf', dxf(lwpoly([(x / 10, y / 10) for x, y in RECT]), insunits=5))
ecrire('dxf/unites_pouces.dxf', dxf(lwpoly([(x / 25.4, y / 25.4) for x, y in RECT]), insunits=1))
ecrire('dxf/unites_absentes_metres.dxf', dxf(lwpoly([(x / 1000, y / 1000) for x, y in RECT]), insunits=None))
ecrire('dxf/encodage_cp1252.dxf', dxf(lwpoly(RECT) + g(0, 'TEXT') + g(8, 'A-ANNO') + g(10, 100) + g(20, 100) + g(40, 100) + g(1, 'Séjour é')), 'wb' if False else 'w', 'cp1252')

# CSV
ecrire('csv/rectangle_xy.csv', 'x;y\n0;0\n4000;0\n4000;3000\n0;3000\n')
ecrire('csv/l_xy.csv', 'x;y\n' + ''.join(f'{x};{y}\n' for x, y in L))
ecrire('csv/pan_coupe_xy.csv', 'x,y\n' + ''.join(f'{x},{y}\n' for x, y in PAN))
ecrire('csv/rectangle_metres.csv', 'x;y\n0,0;0,0\n4,0;0,0\n4,0;3,0\n0,0;3,0\n')
ecrire('csv/rectangle_cotes.csv', 'cote;angle\n4000;90\n3000;90\n4000;90\n3000;90\n')
ecrire('csv/l_cotes.csv', 'cote;angle\n5000;90\n2000;90\n3000;270\n2000;90\n2000;90\n4000;90\n')
ecrire('csv/pan_coupe_cotes.csv', 'cote;angle\n4000;90\n2000;135\n1414.2136;135\n3000;90\n3000;90\n')
ecrire('csv/lignes_invalides.csv', 'x;y\n0;0\n4000;0\nabc;3000\n4000;\n0;3000\n')
ecrire('csv/vide.csv', 'x;y\n')
ecrire('csv/ouvert_cotes.csv', 'cote;angle\n4000;90\n3000;90\n3500;90\n3000;90\n')
