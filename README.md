# Système de cartes électroniques avec QR code — version PHP

Génère, à partir d'un fichier Excel, les cartes professionnelles de vos employés :
- une page web de profil par personne (affichée quand on scanne son QR code),
- un QR code par personne,
- un PDF imprimable avec toutes les cartes (recto : ID + QR, verso : logo).

Équivalent PHP de la version Python fournie précédemment — même logique, mêmes fichiers de
données, même design, pour que vous puissiez l'héberger sur un serveur PHP mutualisé classique.

## 1. Prérequis

- PHP 8.1 ou plus récent, avec les extensions **gd** et **mbstring** (présentes par défaut
  chez la plupart des hébergeurs mutualisés).
- [Composer](https://getcomposer.org) pour installer les 3 bibliothèques utilisées :
  - `phpoffice/phpspreadsheet` — lecture du fichier Excel
  - `endroid/qr-code` — génération des QR codes
  - `dompdf/dompdf` — génération du PDF imprimable

```bash
composer install
```

> Note : je n'ai pas pu exécuter le pipeline complet dans mon environnement de test car
> l'accès à Packagist (le registre de paquets Composer) y est bloqué par le réseau. J'ai
> testé unitairement toute la logique qui ne dépend pas de ces 3 bibliothèques (lecture des
> données, gabarit de page, avatar de secours). Une fois `composer install` lancé chez vous,
> testez avec 2-3 employés avant de lancer les 200.

## 2. Préparer vos données

1. Ouvrez `data/employees.xlsx` et remplissez une ligne par employé (Prénom, Nom, Téléphone,
   Adresse, Nom du fichier photo). L'ID se génère tout seul. **Supprimez la ligne d'exemple
   en jaune avant de lancer le script.**
2. Placez les photos correspondantes dans le dossier `photos/`, avec exactement le nom indiqué
   dans le fichier Excel (colonne "Nom du fichier photo"). Une photo manquante n'empêche rien :
   un avatar de secours avec les initiales sera généré automatiquement à sa place.
3. (Optionnel) Placez votre logo dans `assets/` et indiquez son chemin dans `config.php`
   (`'logo_path' => 'assets/logo.png'`). Sans logo fourni, un repère générique est utilisé.

## 3. Configurer

Ouvrez `config.php` et modifiez au minimum :
- `company_name` : le nom de votre entreprise
- `base_url` : l'adresse où vous allez héberger les pages générées (voir section 5)
- éventuellement les couleurs (`accent_color`, `ink_color`) et `logo_path`

## 4. Générer

```bash
php src/generate_all.php
```

Cela produit dans `output/` :
- `pages/<ID>.html` — une page de profil par employé
- `qrcodes/<ID>.png` — un QR code par employé, pointant vers `base_url/<ID>.html`
- `Cartes_Imprimables.pdf` — toutes les cartes recto/verso, prêtes à envoyer à un imprimeur
  (format CR80, standard carte de crédit / badge, 85.6 × 54 mm)

Relancez le script à chaque mise à jour du fichier Excel ou des photos ; tout est régénéré.

## 5. Héberger les pages de profil

Le QR code ne fonctionne que si les pages dans `output/pages/` sont mises en ligne à
l'adresse indiquée par `base_url`. Comme ce sont de simples fichiers HTML autonomes (aucun
serveur PHP requis pour les servir), plusieurs options :

**Sur votre hébergement PHP existant**
Déposez le contenu de `output/pages/` dans un dossier accessible publiquement (ex: un
sous-dossier `cartes/` à la racine du site), à l'adresse que vous mettez dans `base_url`.

**GitHub Pages (gratuit, si vous préférez séparer ça de votre serveur PHP)**
1. Créez un dépôt GitHub, activez GitHub Pages sur la branche principale.
2. Copiez le contenu de `output/pages/` à la racine du dépôt (ou dans un sous-dossier `cartes/`).
3. Réglez `base_url` dans `config.php` en conséquence, puis relancez `generate_all.php`.

Dans tous les cas : **générez d'abord les QR codes une fois `base_url` définitivement fixé**,
sinon les cartes déjà imprimées pointeront vers la mauvaise adresse.

## 6. Imprimer

Envoyez `output/Cartes_Imprimables.pdf` à un imprimeur de cartes (format CR80, PVC).
Le PDF contient une page recto puis une page verso pour chaque employé, dans cet ordre.

## Structure du projet

```
composer.json                   → dépendances PHP
config.php                      → tous les réglages (entreprise, couleurs, URL, chemins)
data/employees.xlsx             → vos données employés
photos/                         → les photos des employés
assets/                         → votre logo (optionnel)
templates/profile_template.html → gabarit de la page de profil (modifiable)
templates/card_template.html    → gabarit de la carte imprimée, rendu en PDF via dompdf
src/generate_all.php            → le script qui génère tout
output/                         → tout ce qui est généré (pages, QR codes, PDF)
```

## Personnaliser le design

`templates/profile_template.html` et `templates/card_template.html` sont de simples fichiers
HTML/CSS avec des jetons `{{...}}` remplacés par le script — modifiables librement (couleurs,
polices, disposition, champs supplémentaires). Le PDF est généré à partir du second gabarit
via dompdf ; toute modification CSS compatible avec dompdf (voir sa documentation) fonctionne.
