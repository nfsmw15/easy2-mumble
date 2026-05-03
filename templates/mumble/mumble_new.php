<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble - Neuer Server (Dashboard)
 * File: templates/mumble/mumble_new.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-danger mt-4">Mumble-Erweiterung nicht initialisiert.</div></div></div>';
    return;
}
if (!$mumble->canCreate()) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-warning mt-4">Keine Berechtigung zum Anlegen von Mumble-Servern.</div></div></div>';
    return;
}

$mb_uid    = (int)$loginsystem->getUser('id');
$mb_rank   = (int)$loginsystem->getUser('rank');
$mb_quota  = $mumble->getQuotaForRank($mb_rank);
$mb_owned  = $mumble->countServersByOwner($mb_uid);
$mb_hosts  = $mumble->listHosts(true);
$mb_csrf   = (string)$loginsystem->getData('csrfToken');

if (!$mumble->canAdminAll() && $mb_owned >= (int)$mb_quota['max_servers']) {
    echo '<div class="content-wrapper"><div class="container full-container">'
       . '<div class="alert alert-warning mt-4">'
       . 'Du hast dein Server-Kontingent (' . (int)$mb_quota['max_servers']
       . ') bereits erreicht.</div></div></div>';
    return;
}
if (empty($mb_hosts)) {
    echo '<div class="content-wrapper"><div class="container full-container">'
       . '<div class="alert alert-warning mt-4">'
       . 'Es ist kein aktiver Mumble-Host konfiguriert. Bitte einen Administrator kontaktieren.</div>'
       . '</div></div>';
    return;
}
?>
<div class="content-wrapper">
<div class="container full-container">
    <h1 class="mt-4 mb-3"><i class="fa fa-plus"></i> Neuer Mumble-Server</h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="?">&Uuml;bersicht</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble">Mumble</a></li>
        <li class="breadcrumb-item active">Neuer Server</li>
    </ol>

    <?php echo $error; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><i class="fa fa-headphones"></i> Server-Daten</div>
                <div class="card-body">
                    <form action="?p=mumble_new&c=create" method="post">
                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">

                        <div class="form-group">
                            <label for="mb-name">Name *</label>
                            <input type="text" class="form-control" id="mb-name" name="name"
                                   maxlength="64" required
                                   placeholder="z.B. MSG Meisenthal Sprachkanal"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            <small class="form-text text-muted">
                                Erlaubte Zeichen: Buchstaben, Zahlen, Leerzeichen, Bindestrich, Unterstrich, Punkt
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="mb-host">Host *</label>
                            <select class="form-control" id="mb-host" name="host_id" required>
                                <?php foreach ($mb_hosts as $h) { ?>
                                    <option value="<?php echo (int)$h['id']; ?>"
                                        <?php echo ((int)($_POST['host_id'] ?? 0) === (int)$h['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string)$h['name']); ?>
                                        (<?php echo htmlspecialchars((string)$h['hostname']); ?>)
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="mb-pw">Server-Passwort <small class="text-muted">(optional)</small></label>
                            <input type="text" class="form-control" id="mb-pw" name="password"
                                   maxlength="64" autocomplete="off"
                                   placeholder="leer = öffentlich"
                                   value="<?php echo htmlspecialchars($_POST['password'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="mb-max">Maximale Nutzer</label>
                            <input type="number" class="form-control" id="mb-max" name="max_users"
                                   min="1" max="<?php echo (int)$mb_quota['max_users_cap']; ?>"
                                   value="<?php echo htmlspecialchars((string)($_POST['max_users'] ?? 10)); ?>">
                            <small class="form-text text-muted">
                                Dein Rang erlaubt bis zu <?php echo (int)$mb_quota['max_users_cap']; ?> gleichzeitige Nutzer pro Server.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="mb-welcome">Begrüßungstext <small class="text-muted">(optional)</small></label>
                            <textarea class="form-control" id="mb-welcome" name="welcome_text"
                                      rows="4" maxlength="2000"><?php echo htmlspecialchars($_POST['welcome_text'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check"></i> Server anlegen
                        </button>
                        <a href="?p=mumble" class="btn btn-secondary">
                            <i class="fa fa-times"></i> Abbrechen
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header"><i class="fa fa-info-circle"></i> Dein Kontingent</div>
                <div class="card-body">
                    <p class="mb-1"><strong>Eigene Server:</strong></p>
                    <?php
                    $cap = (int)$mb_quota['max_servers'];
                    $pct = $cap > 0 ? min(100, (int)round($mb_owned / $cap * 100)) : 0;
                    ?>
                    <div class="progress mb-3" style="height:24px;">
                        <div class="progress-bar bg-info" role="progressbar"
                             style="width: <?php echo $pct; ?>%;">
                            <?php echo $mb_owned; ?> / <?php echo $cap; ?>
                        </div>
                    </div>
                    <p class="mb-1"><strong>Max. Nutzer pro Server:</strong></p>
                    <p><?php echo (int)$mb_quota['max_users_cap']; ?></p>
                    <hr>
                    <p class="text-muted small mb-0">
                        Nach dem Anlegen wird der Server automatisch gestartet.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
