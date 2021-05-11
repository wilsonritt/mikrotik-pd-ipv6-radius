<?php
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB . ';port=' . DB_PORT, DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "<h1>Erro ao conectar no banco de dados!</h1>";
    die();
}

if (isset($_POST['search-button'])) {
    $searchString = $_POST['search-string'];
    $searchField = $_POST['search-field'];
    $startDate = $_POST['start-date'];
    $endDate = $_POST['end-date'];

    switch ($searchField) {
        case "username":
            // Query by MAC
            $sql = $pdo->prepare("SELECT username,nasipaddress,acctstarttime,acctstoptime,calledstationid,delegatedipv6prefix,mikrotikrealm FROM radacct WHERE acctstarttime > ? AND acctstarttime < ? AND username LIKE ?");
            break;

        case "nasipaddress":
            // Query by NAS
            $sql = $pdo->prepare("SELECT username,nasipaddress,acctstarttime,acctstoptime,calledstationid,delegatedipv6prefix,mikrotikrealm FROM radacct WHERE acctstarttime > ? AND acctstarttime < ? AND nasipaddress=?");
            break;

        case "mikrotikrealm":
            // Query by SITE
            $sql = $pdo->prepare("SELECT username,nasipaddress,acctstarttime,acctstoptime,calledstationid,delegatedipv6prefix,mikrotikrealm FROM radacct WHERE acctstarttime > ? AND acctstarttime < ? AND mikrotikrealm LIKE ?");
            break;

        case "calledstationid":
            // Query by USER
            $sql = $pdo->prepare("SELECT username,nasipaddress,acctstarttime,acctstoptime,calledstationid,delegatedipv6prefix,mikrotikrealm FROM radacct WHERE acctstarttime > ? AND acctstarttime < ? AND calledstationid LIKE ?");
            break;

        case "delegatedipv6prefix":
            // Query by PD
            $sql = $pdo->prepare("SELECT username,nasipaddress,acctstarttime,acctstoptime,calledstationid,delegatedipv6prefix,mikrotikrealm FROM radacct WHERE acctstarttime > ? AND acctstarttime < ? AND delegatedipv6prefix LIKE ?");
            break;
    }

    $sql->execute(array($startDate, $endDate, $searchString));
    $count = $sql->rowCount();
    if ($count == 0) {
        $info = '';
    } else {
        $info = $sql->fetchAll();
    }
}
?>
<div class="container">
    <div class="filter-menu center">
        <form name="filter-form" onsubmit="return validateForm()" method="post">
            <div class="filter-top">
                <div class="w50 left">
                    <label for="search-string">Pesquisar por:</label>
                    <input class="filters w100" type="text" name="search-string" placeholder="2001:db8:1432:a4b2::/56 ou <pppoe-joao%" autocomplete="off" required>
                    <label for="search-field">Campo de Busca:</label>
                    <select class="filters w100" id="search-field" name="search-field" required>
                        <option></option>
                        <option value="username">MAC</option>
                        <option value="nasipaddress">Concentrador (IP)</option>
                        <option value="mikrotikrealm">Site (NOME)</option>
                        <option value="calledstationid">Usuário</option>
                        <option value="delegatedipv6prefix">DHCPv6 PD</option>
                    </select>
                </div>
                <div class="w50 right">
                    <label>Início:</label>
                    <input class="filters w100" name="start-date" type="date" value="2020-12-31" required>
                    <label>Fim:</label>
                    <input class="filters w100" name="end-date" type="date" <?php echo 'value="' . date("Y-m-d") . '" '; ?> required>
                </div>
                <div class="clear"></div>
            </div>
            <div class="filter-button">
                <button class="search-button" onclick="window.location.href='.'" name="reset-button">Reset</button>
                <button class="search-button" name="search-button" type="submit">Pesquisar</button>
            </div>
            <div class="clear"></div>
        </form>
    </div>
    <?php if (isset($_POST['search-button'])) {
        echo '<table>';
        echo '<tr>';
        echo '<th>Usuário</th>';
        echo '<th>NAS (IP)</th>';
        echo '<th>Site</th>';
        echo '<th>Início</th>';
        echo '<th>Fim</th>';
        echo '<th>MAC</th>';
        echo '<th>Prefixo Delegado</th>';
        echo '</tr>';

        if ($count) {
            foreach ($info as $key => $value) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($value['calledstationid']) . '</td>';
                echo '<td>' . $value['nasipaddress'] . '</td>';
                echo '<td>' . $value['mikrotikrealm'] . '</td>';
                echo '<td>' . $value['acctstarttime'] . '</td>';
                if (is_null($value['acctstoptime'])) {
                    echo '<td style="color: green;">ONLINE</td>';
                } else {
                    echo '<td>' . $value['acctstoptime'] . '</td>';
                }
                echo '<td>' . $value['username'] . '</td>';
                echo '<td>' . $value['delegatedipv6prefix'] . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr>';
            echo '<td colspan="7" style="text-align: center;">Sua busca não retornou resultados</td>';
            echo '</tr>';
        }
    ?>
        </table>
    <?php
    }
    ?>
</div>