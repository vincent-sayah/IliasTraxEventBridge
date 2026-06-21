# Objectif 02.1 — Nettoyage du mapping xAPI local

## But

Éviter que des actions d’administration ILIAS soient transformées en traces d’apprentissage xAPI.

## Cas corrigé

La suppression des résultats de test émet des événements ILIAS :

```text
components/ILIAS/Tracking:updateStatus
cmdClass=ilTestParticipantsGUI
pt_action=delete_results
```

Ces événements doivent rester visibles dans le journal debug, mais ils ne doivent pas générer de statement xAPI.

## Règles V0.2.1

### Ignoré pour xAPI

```text
URI contient cmdClass=ilTestParticipantsGUI
ou URI contient pt_action=delete_results
ou URI contient cmd=executeTableAction
```

### Test transformé en xAPI

```text
Tracking/updateStatus
et obj_type=tst
ou URI contient ilTestPlayerFixedQuestionSetGUI
ou URI contient ilTestPlayerDynamicQuestionSetGUI
ou URI contient cmd=startTest
ou URI contient cmd=finishTest
```

### Tracking non-test

```text
Tracking/updateStatus avec obj_type non vide
=> learning_tracking_status
```

### Tracking ambigu

```text
Tracking/updateStatus avec obj_type vide
et ne venant pas du player de test
=> journalisé mais ignoré pour l’outbox
```
