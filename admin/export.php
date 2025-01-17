<?php
/**
 * Dokuwiki Advanced Import/Export Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 */

class admin_plugin_advanced_export extends DokuWiki_Admin_Plugin
{

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 2;
    }

    public function getMenuIcon()
    {
        return dirname(__FILE__) . '/../svg/export.svg';
    }

    public function forAdminOnly()
    {
        return false;
    }

    public function getMenuText($language)
    {
        return $this->getLang('menu_export');
    }

    public function handle()
    {
        global $INPUT;

        if (!$_REQUEST['cmd'])
        {
            return;
        }

        if (!checkSecurityToken())
        {
            return;
        }

        $cmd = $INPUT->extract('cmd')
            ->str('cmd');

        if ($cmd)
        {
            $cmd = "cmd_$cmd";
            $this->$cmd();
        }

    }

    public function html()
    {

        global $INPUT;
        global $lang;
        global $conf;
        global $ID;

        $lang['toc'] = $this->getLang('menu_export');

        echo '<div id="plugin_advanced_export">';
        echo $this->locale_xhtml('export');

        echo '<form action="" method="post" class="form-inline">';

        $this->steps_dispatcher();

        formSecurityToken();

        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="page" value="advanced_export" />';

        echo '</form>';
        echo '</div>';

    }

    private function steps_dispatcher()
    {

        global $INPUT;

        $step = $INPUT->extract('export')
            ->str('export');

        if (!$step)
        {
            return $this->step_select_ns();
        }

        return call_user_func(array(
            $this,
            "step_$step"
        ));

    }

    private function step_select_ns()
    {

        global $conf;
        global $lang;

        $namespaces = array();
        $options = array();

        search($namespaces, $conf['datadir'], 'search_namespaces', $options, '');

        echo sprintf('<h3>%s</h3>', $this->getLang('exp_select_namespace'));

        echo '<p><select name="ns" class="form-control">';
        echo '<option value="">' . $this->getLang('exp_select_namespace') . '</option>';
        echo '<option value="(root)">(root)</option>';

        foreach ($namespaces as $namespace)
        {
            echo sprintf('<option value="%s">%s</option>', $namespace['id'], $namespace['id']);
        }

        echo '</select></p>';

        echo '<h3>Options</h3>';
        echo '<table class="table inline"><tbody>';

        echo '<tr>
        <td width="10"><input type="checkbox" name="export-media" /></td>
        <td>Export media</td>
      </tr>';
        echo sprintf('<tr>
        <td width="10"><input type="checkbox" name="include-sub-ns" /></td>
        <td>%s</td>
      </tr>', $this->getLang('exp_include_sub_namespaces'));

        echo "</tbody></table>";

        echo '<input type="hidden" name="step" value="select-ns" />';

        echo '<p class="pull-right">';
        echo sprintf('<button type="submit" name="cmd[export]" class="btn btn-default">%s &rarr;</button> ', $this->getLang('exp_export_all_pages_in_namespace'));
        echo sprintf('<button type="submit" name="export[select_pages]" class="btn btn-primary primary">%s &rarr;</button> ', $this->getLang('exp_select_pages'));
        echo '</p>';

    }

    private function getPagesFromNamespace($ns, $follow_ns = 0)
    {

        global $conf;

        $depth = ($follow_ns ? 0 : 2);

        if ($ns == '(root)')
        {
            $ns = '';
            $depth = ($follow_ns ? 2 : 1);
        }

        $pages = array();
        $namespace = str_replace(':', '/', $ns);
        $options = array(
            'depth' => $depth
        );

        search($pages, $conf['datadir'], 'search_allpages', $options, $namespace);

        return $pages;

    }

    private function step_select_pages()
    {

        global $INPUT;
        global $conf;
        global $lang;

        $pages = array();
        $namespace = str_replace(':', '/', $INPUT->str('ns'));

        if (!$namespace)
        {
            msg($this->getLang('exp_no_namespace_selected') , -1);
            $this->step_select_ns();
            return 0;
        }

        $pages = $this->getPagesFromNamespace($INPUT->str('ns') , ($INPUT->str('include-sub-ns') ? 1 : 0));

        echo sprintf('<h3>%s</h3>', $this->getLang('exp_select_pages'));
        echo sprintf('<input type="hidden" value="%s" name="ns" />', $INPUT->str('ns'));
        echo sprintf('<input type="hidden" value="%s" name="export-media" />', $INPUT->str('export-media'));

        echo '<table class="table inline pages" width="100%">';
        echo '<thead>
      <tr>
        <th width="10"><input type="checkbox" class="export-all-pages" title="' . $this->getLang('select_all_pages') . '" /></th>
        <th>Page</th>
        <th>Created</th>
        <th>Modified</th>
        <th>Size</th>
      </tr>
    </thead>';
        echo '<tbody>';

        foreach ($pages as $page)
        {

            $page_id = $page['id'];
            $page_title = p_get_first_heading($page_id);
            $page_size = filesize_h($page['size']);
            $create_user = editorinfo(p_get_metadata($page_id, 'user'));
            $modified_user = editorinfo(p_get_metadata($page_id, 'last_change user'));
            $create_date = dformat(p_get_metadata($page_id, 'date created'));
            $modified_date = dformat(p_get_metadata($page_id, 'date modified'));

            echo sprintf('
        <tr>
          <td><input type="checkbox" name="pages[%s]" class="export-page" /></td>
          <td>%s<br/><small>%s</small></td>
          <td>%s<br/>%s</td>
          <td>%s<br/>%s</td>
          <td>%s</td>
        </tr>', $page_id, $page_id, $page_title, $create_user, $create_date, $modified_user, $modified_date, $page_size);

        }

        echo '</tbody>';
        echo '</table>';

        echo '<p>&nbsp;</p>';
        echo '<input type="hidden" name="step" value="select-pages" />';

        echo '<p class="pull-right">';
        echo sprintf('<button type="submit" name="export[select_ns]" class="btn btn-default">&larr; %s</button> ', $lang['btn_back']);
        echo sprintf('<button type="submit" name="cmd[export]" class="btn btn-primary primary">%s &rarr;</button>', $this->getLang('btn_export'));
        echo '</p>';

    }

    private function list_dir($dir, &$results = array())
    {
        $files = scandir($dir);

        foreach ($files as $key => $value)
        {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path))
            {
                $results[] = $path;
            }
            else if ($value != "." && $value != "..")
            {
                $this->list_dir($path, $results);
                $results[] = $path;
            }
        }

        return $results;
    }

    private function cmd_export()
    {

        global $INPUT;
        global $conf;

        $pages = array();
        $export_media = $INPUT->str('export-media') ? 1 : 0;

        switch ($INPUT->str('step'))
        {

            case 'select-ns':

                foreach ($this->getPagesFromNamespace($INPUT->str('ns') , ($INPUT->str('include-sub-ns') ? 1 : 0)) as $page)
                {
                    $pages[] = $page['id'];
                }

            break;

            case 'select-pages':
                $pages = array_keys($INPUT->arr('pages'));
            break;

        }

        if (!count($pages))
        {
            msg('No page selected for export!', -1);
            return 0;
        }

        $namespace = str_replace(':', '-', str_replace('(root)', 'ROOT', $INPUT->str('ns')));
        $timestamp = date('Ymd-His');
        $dir = utf8_encodeFN(str_replace(':', '/', $namespace));
        if ($namespace == 'ROOT')
        {
            $ns_media_dir = $conf['mediadir'];
        }
        else
        {
            $ns_media_dir = $conf['mediadir'] . DIRECTORY_SEPARATOR . $dir;
        }
        $media_files = array();
        $this->list_dir($ns_media_dir, $media_files);

        $Zip = new ZipArchive;
        $zip_dir = io_mktmpdir();
        $zip_file_name = $zip_dir . DIRECTORY_SEPARATOR . "file.zip";
        $res = $Zip->open($zip_file_name, ZipArchive::CREATE);

        if ($export_media)
        {
            dbglog('Export media');
            foreach ($media_files as $path)
            {
                $full_path = utf8_encodeFN($path);
                $zip_path = str_replace($conf['mediadir'], '', $full_path);
                $zip_path = str_replace(str_replace('/opt', '', $conf['mediadir']) , '', $full_path);
                $zip_path = '.media' . $zip_path;

                if (file_exists($full_path) && is_readable($full_path) && !is_dir($full_path))
                {
                    $res = $Zip->addFile($full_path, $zip_path);
                    dbglog("Export: $full_path - $zip_path");
                }
            }
        }

        foreach ($pages as $page)
        {

            $file_fullpath = wikiFN($page);
            $file_path = str_replace($conf['datadir'], '', $file_fullpath);

            $Zip->addFile($file_fullpath, $file_path);

        }
        $res = $Zip->close();
        dbglog($res);

        header("Content-Type: application/zip");
        header("Content-Transfer-Encoding: Binary");
        header("Content-Disposition: attachment; filename=DokuWiki-export-$namespace-$timestamp.zip");

        readfile($zip_file_name);
        io_rmdir($zip_dir);

        die();

    }

}
