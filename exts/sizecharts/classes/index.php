<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'sizecharts_tables.php';

//18-10-2024
final class BOTOSCOPE_SIZECHARTS extends BOTOSCOPE_APP {

    protected $table_name = 'botoscope_sizecharts';
    protected $data_structure = [
        'title' => 'click to edit ...',
        'description' => '',
        'is_default' => 0,
        'is_active' => 0
    ];
    protected $chart_tables;

    public function __construct($args=[]) {
        parent::__construct($args);
        $this->chart_tables = new BOTOSCOPE_SIZECHARTS_TABLES($args);
    }

    public function get($page_num = 0) {
        $res = parent::get();

        if (!empty($res)) {
            foreach ($res as $k => $st) {
                $res[$k]['table'] = $this->chart_tables->get_ids($st['id']);
            }
        }

        return $res;
    }

    public function update($id, $field_key, $value, $all_sent_data = []) {
        if (empty($id) || empty($field_key)) {
            return false;
        }

        if ($field_key === 'is_default') {
            $all = $this->get();

            foreach ($all as $sch) {
                if (intval($sch['id']) !== intval($id)) {
                    $this->db->update($this->table_name, [$field_key => 0], ['id' => intval($sch['id'])]);
                }
            }
        }

        return $this->db->update($this->table_name, [$field_key => $value], ['id' => intval($id)]);
    }

    public function get_chart_tables($id) {
        return (array) $this->chart_tables->get_of($id);
    }

    public function delete_chart_table($chid) {
        $this->chart_tables->delete($chid);
    }

    public function update_chart_table_cell($id, $key, $value) {
        $this->chart_tables->update($id, $key, $value);
    }

    public function create_chart_table($sizechart_id) {
        $data_structure = $this->chart_tables->create([
            'sizechart_id' => $sizechart_id
        ]);

        return $data_structure;
    }

    public function delete($id, $conditions = []) {

        $chart_tables_ids = $this->chart_tables->get_ids($id);

        if (!empty($chart_tables_ids)) {
            foreach ($chart_tables_ids as $chid) {
                $this->chart_tables->delete($chid);
            }
        }

        parent::delete($id);
    }

    public function get_active() {

        $res = [];
        $rows = $this->get();

        if (!empty($rows)) {
            foreach ($rows as $r) {

                if (!intval($r['is_active'])) {
                    continue;
                }

                $r['table'] = [];
                $r['id'] = intval($r['id']);
                $tables = $this->get_chart_tables($r['id']);

                if (!empty($tables)) {
                    foreach ($tables as $row) {

                        $chid = $row['id'];
                        unset($row['id']);
                        unset($row['sizechart_id']);
                        $r['table'][$chid] = [];

                        foreach ($row as $key => $value) {
                            $range = explode(',', $value);
                            $r['table'][$chid][$key] = array_map('intval', $range);
                        }
                    }
                }

                unset($r['is_default']);
                unset($r['is_active']);

                array_push($res, $r);
            }
        }

        return $res;
    }

    public function draw_content($counter) {
        ?>

        <section id="botoscope-sizecharts" <?php if ($counter === 0): ?>class="content-current"<?php endif; ?>>

            <div id="botoscope-sizecharts-w"><?php echo wp_json_encode($this->get(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></div>
            <br>
            <a href="javascript: void(0);" id="botoscope_create_sizecharts" class="button button-primary"><?php esc_html_e('New sizechart item', 'botoscope') ?></a><br>

        </section>

        <?php
    }
}
