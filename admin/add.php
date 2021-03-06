<?php
// todo: handle errors
$ROOT = "../";
include($ROOT.'partial/header.php');

echo "<section>";

if (isset($_SESSION['pseudo'])) {
    if (isset($_REQUEST['period']) && ($_REQUEST['period'] != "") && !empty($_REQUEST['titre_arc']) && !empty($_REQUEST['contenu']) && isset($_REQUEST['formfilled']) && $_REQUEST['formfilled'] == 42) {
        // todo: sanitize $_REQUEST
        $era    = $_REQUEST['era'];
        $period = $_REQUEST['period'];

        $is_event = 0;
        if (isset($_REQUEST['isEvent']) && $_REQUEST['isEvent'] == "on") $is_event = 1;

        echo "<div class='form'>";

        $upload = uploadCover($_FILES['cover']);

        if ($upload[0] === true) {
            // Fetch all periods from era requested
            $periods        = fetchPeriods($era);
            $n              = count($periods);
            $id_last_period = $periods[$n];
            $req            = $bdd->prepare("INSERT INTO arc(id_period, position, title, cover, content, link_a, link_b, is_event)
                                VALUES(:id_period, :position, :title, :cover, :content, :link_a, :link_b, :is_event)");
            $cover          = $upload[1];
            if (!empty($_REQUEST['id']) && ($_REQUEST['id'] != '0')) {
                $position        = $_REQUEST['id'];
                $where_clause    = "";
                $period_position = array_search($period, $periods);
                $periods_to_move = array_slice($periods, ($period_position-1));
                $n               = count($periods_to_move);
                $i               = 0;
                foreach ($periods_to_move as $period_to_move) {
                    ++$i;
                    $where_clause .= "id_period = '$period_to_move'";
                    if ($i < $n) {
                        $where_clause .= " OR ";
                    }
                }
                $sql   = "UPDATE arc SET position = position + 1 WHERE position >= $position AND ($where_clause)";
                $bdd->query($sql);
            } else {
                $where_clause    = "";
                $n               = count($periods);
                $i               = 0;
                foreach ($periods as $period) {
                    ++$i;
                    $where_clause .= "id_period = '$period'";
                    if ($i < $n) {
                        $where_clause .= " OR ";
                    }
                }
                $sql      = "SELECT MAX(position) FROM arc WHERE $where_clause";
                $maxid    = $bdd->query($sql)->fetch(PDO::FETCH_COLUMN);
                $position = ++$maxid;
            }

            $req->execute([
                'id_period' => $period,
                'position'  => $position,
                'title'     => htmlentities($_REQUEST['titre_arc']),
                'cover'     => $cover,
                'content'   => htmlentities($_REQUEST['contenu']),
                'link_a'    => $_REQUEST['urban'],
                'link_b'    => $_REQUEST['dctrad'],
                'is_event'  => $is_event
            ]);

            echo $_REQUEST['titre_arc']." a bien été ajouté à l'ODL";

            // Changelog
            $name_period   = $bdd->query("SELECT name FROM period WHERE id_period = '$period'")->fetch(PDO::FETCH_COLUMN);
            $name_era      = $bdd->query("SELECT name FROM era WHERE id_era = '$era'")->fetch(PDO::FETCH_COLUMN);
            $name_universe = $bdd->query("SELECT name FROM universe WHERE id_universe = (SELECT id_universe FROM era WHERE id_era = '$era')")->fetch(PDO::FETCH_COLUMN);

            $changelog = array(
                'name_universe' => $name_universe,
                'name_era'      => $name_era,
                'name_period'   => $name_period,
                'position'      => $position,
                'title'         => htmlentities($_REQUEST['titre_arc']),
            );

            $query = $bdd->prepare("INSERT INTO changelog(author, cl_type, name_universe, name_era, name_period, old_position, new_position, title, new_title, cover, content, urban, dctrad, isEvent)
                                VALUES(:author, :cl_type, :name_universe, :name_era, :name_period, :old_position, :new_position, :title, :new_title, :cover, :content, :urban, :dctrad, :isEvent)");
            $query->execute(array(
                'author'        => $_SESSION['pseudo'],
                'cl_type'       => 'add',
                'name_universe' => $changelog['name_universe'],
                'name_era'      => $changelog['name_era'],
                'name_period'   => $changelog['name_period'],
                'old_position'  => '',
                'new_position'  => $changelog['position'],
                'title'         => $changelog['title'],
                'new_title'     => '',
                'cover'         => '',
                'content'       => '',
                'urban'         => '',
                'dctrad'        => '',
                'isEvent'       => $is_event
            ));

            $count = $bdd->query("SELECT count(*) FROM changelog")->fetch(PDO::FETCH_COLUMN);
            if ($count > 100) {
                $bdd->exec("DELETE FROM changelog ORDER BY id ASC LIMIT 1");
            }

            echo ".";
            echo "<a href='/admin/add.php'><button type='button' class='btn_head'>Retour au formulaire</button></a>";
            echo "<a href='/index.php'><button type='button' class='btn_head'>Retour à l'accueil</button></a>";
            echo "</div>";
        } else {
            print_r($upload[1]);
            echo "<a href='/admin/add.php'><button type='button' class='btn_head'>Retour au formulaire</button></a>";
        }
    } else {
        $universes = [];
        $universe_query = $bdd->query("SELECT * FROM universe");

        $current_universe = "";
        if (!empty($_SESSION['last_era_visited'])) {
            $current_era      = $_SESSION['last_era_visited'];
            $current_universe = $bdd->query("SELECT id_universe FROM era WHERE id_era = '$current_era'")->fetch(PDO::FETCH_COLUMN);
        }
?>
    <div class="form">
        <h2>Ajouter un arc</h2>
        <form action="?" method="POST" enctype="multipart/form-data">
            <div class="head_form">
                <div class="info_sections">
                    <input type="hidden" name="formfilled" value="42" />
                    <select name="universe" required>
                        <option value="">Universe</option>
                        <?php
                        while ($universe = $universe_query->fetch(PDO::FETCH_ASSOC)) {
                            $isSelected = "";
                            if ($current_universe == $universe['id_universe']) {
                                $isSelected = "selected";
                            }
                            echo "<option value=\"{$universe['id_universe']}\" $isSelected>{$universe['name']}</option>\n";
                        }
                        ?>
                    </select><br />
                    <select name="era" required>
                        <option value="">Era</option>
                    </select><br />
                    <select name="period" required>
                        <option value="">Période</option>
                    </select>
                </div>
                <div class="div_checkbox">
                    <label for="checkboxIsEvent">Event ?</label><br />
                    <input type="checkbox" name="isEvent" id="checkboxIsEvent" <?php if(isset($_REQUEST['isEvent'])) echo "checked"; ?>>
                </div>
            </div>
            <input type="text" class="input" name="titre_arc" placeholder="Titre de l'arc" value="<?= @$_REQUEST['titre_arc'] ?>" required><br />
            <label for="cover">Cover</label>
            <input type="hidden" name="MAX_FILE_SIZE" value="1048576" />
            <input type="file" class="file" name="cover" accept="image/*" required><br />
            <textarea class="content" name="contenu" placeholder="Liste des issues de l'arc" required><?= @$_REQUEST['contenu'] ?></textarea><br />
            <div class="isUrban_DCT">
                <div class="isUrban">
                    <label for="CBisUrban">VF</label>
                    <input type="checkbox" name="isUrban" id="CBisUrban" <?php if(isset($_REQUEST['urban']) && !empty($_REQUEST['urban'])) echo "checked"; ?>>
                </div>
                <div class="isDCT">
                    <label for="CBisDCT">DCTrad</label>
                    <input type="checkbox" name="isDCT" id="CBisDCT" <?php if(isset($_REQUEST['dctrad']) && !empty($_REQUEST['dctrad'])) echo "checked"; ?>>
                </div>
            </div>
            <input type="url" class="input" name="urban" id="LinkUrban"
            <?php
            if (isset($_REQUEST['urban']) && !empty($_REQUEST['urban'])) {
                echo "style='display: block;'";
            } else {
                echo "style='display: none;'";
            }
            ?>
            placeholder="https://www.mdcu-comics.fr/comics-vo/comics-vo-44779" value="<?= @$_REQUEST['urban'] ?>">
            <input type="url" class="input" name="dctrad" id="LinkDCT"
            <?php
            if (isset($_REQUEST['dctrad']) && !empty($_REQUEST['dctrad'])) {
                echo "style='display: block;'";
            } else {
                echo "style='display: none;'";
            }
            ?>
            placeholder="http://www.dctrad.fr/viewtopic.php?f=257&t=13234" value="<?= @$_REQUEST['dctrad'] ?>">
            <div class="tooltip">
                <span class="tooltiptext">À utiliser pour rajouter entre deux arcs déjà présents. Sinon ne pas remplir.</span>
                <label for="id">Position dans l'ODL</label>
                <input type="number" class="pos" min="0" name="id">
            </div>
            <input type="submit" class="btn_send" value="Envoyer">
        </form>
    </div>
<?php
echo "<div id='preview' class='content_period' style='display: block;'>\n";
echo "\t<table>\n";
echo "\t\t<tr class='line'>\n";
echo "\t\t\t<td class='cel_img'><img src=\"\" style='width: 150px;'></td>\n";
echo "\t\t\t<td class='cel_title'><span><h3></h3></span></td>\n";
echo "\t\t\t<td class='cel_content'><p></p></td>\n";
echo "\t\t\t<td class='cel_publi'>\n";
echo "\t\t\t\t<h4>Disponible chez</h4>\n";
echo "\t\t\t\t<div class='img_publi'>\n";
echo "\t\t\t\t\t<img src='/assets/img/logo_urban_mini.png' id='logo_a' class='logo_opacity'>\n";
echo "\t\t\t\t\t<img src='/assets/img/logo_dct_mini.png' id='logo_b' class='logo_opacity'>\n";
echo "\t\t\t\t</div>\n";
echo "\t\t\t</td>\n";
echo "\t\t</tr>\n";
echo "\t</table>\n";
echo "</div>\n";
    }
} else {
    echo "Veuillez vous connecter pour poursuivre.";
}
echo "</section>";

include($ROOT.'partial/footer.php');

?>
<script src="/assets/js/add-arcs.js"></script>
