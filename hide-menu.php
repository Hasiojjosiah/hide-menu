<?php
/**
 * Hide Menu
 *
 * @package       HIDEMENU
 * @author        Isis Ticoy
 * @version       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:   Hide Menu
 * Plugin URI:
 * Description:   Simple WordPress menu management based on user roles.
 * Version:       1.0.0
 * Author:        Isis Ticoy
 * Author URI:
 * Text Domain:   hide-menu
 * Domain Path:   /languages
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Include your custom code here.
function remove_admin_menus()
{
    global $menu;
    // non-administrator users
    $restrictedMenu = [];
    if (is_user_logged_in() && !current_user_can('manage_options')) {
        $user = wp_get_current_user();
        $roles = ( array ) $user->roles;

        $optionName = 'hm_'.$user->roles[0].'_menu_list';
        $optionsData = json_decode(get_option($optionName), true);
        foreach ($optionsData as $key => $value) {
            if ($value['isHidden']) {
                array_push($restrictedMenu, htmlentities($value['value'][0]));
                array_push($restrictedMenu, htmlentities($value['value'][2]));
            }
        }
    
        // all users
        $restrict = explode(',', 'Links,Comments');
        // WP localization
        $f = create_function('$v,$i', 'return __($v);');
        array_walk($restrict, $f);
        if (!current_user_can('activate_plugins')) {
            array_walk($restrictedMenu, $f);
            $restrict = array_merge($restrict, $restrictedMenu);
        }

        // remove menus
        end($menu);
        while (prev($menu)) {
            $k = key($menu);
            $v = explode(' ', $menu[$k][0]);
            if (in_array(is_null($v[0]) ? '' : $v[0], $restrict)) {
                unset($menu[$k]);
            }
        }
    }
}
add_action('admin_menu', 'remove_admin_menus');

  
function dbi_add_settings_page()
{
    add_options_page('', 'Hide Menu Settings', 'manage_options', 'hide-menu-plugin', 'hide_menu_plugin_settings_page');
}

add_action('admin_menu', 'dbi_add_settings_page');

function hide_menu_plugin_settings_page()
{
    wp_localize_script('wp-api', 'wpApiSettings', array(
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest')
    ));

    /**
     * Get all menus and save to settings
     */

    global $submenu, $menu, $pagenow;
    $menus = [];
    if (current_user_can('manage_options')) {
        foreach ($menu as $key => $value) {
            $x = [
                'key'=>$key,
                'value'=>$value,
                'isHidden'=>false,
            ];
            array_push($menus, $x);
        }
        add_option('hm_default_menu_list', json_encode($menus), '');
        update_option('hm_default_menu_list', json_encode($menus), '');
    } ?>
    
<style>
.switch{position:relative;display:inline-block;width:60px;height:34px}.switch input{opacity:0;width:0;height:0}.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:#ccc;-webkit-transition:.4s;transition:.4s}.slider:before{position:absolute;content:"";height:26px;width:26px;left:4px;bottom:4px;background-color:#fff;-webkit-transition:.4s;transition:.4s}input:checked+.slider{background-color:#2196f3}input:focus+.slider{box-shadow:0 0 1px #2196f3}input:checked+.slider:before{-webkit-transform:translateX(26px);-ms-transform:translateX(26px);transform:translateX(26px)}.slider.round{border-radius:34px}.slider.round:before{border-radius:50%}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.0.0-alpha.1/axios.min.js"
    integrity="sha512-xIPqqrfvUAc/Cspuj7Bq0UtHNo/5qkdyngx6Vwt+tmbvTLDszzXM0G6c91LXmGrRx8KEPulT+AfOOez+TeVylg=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<input type="hidden" value="<?php echo esc_url_raw(rest_url()); ?>" id="root">
<input type="hidden" value="<?php echo wp_create_nonce('wp_rest'); ?>" id="nonce">

<h1>Hide Menu Settings</h1>
<br>

<h2>Manage menus for <select id="select-role"></select> <span style="position: absolute;"
        class="role-spinner spinner is-active"></span></h2>
<h4><i>Enable the switch to hide the menu for specific roles.</i></h4>

<table class="form-table" role="presentation">
    <tbody id="table-body">
    </tbody>
</table>

<p class="submit"><input type="button" name="submit" id="submit" onclick="submit()" class="button button-primary"
        value="Save Changes"> <span style="position: absolute; display:none"
        class="submit-spinner spinner is-active"></span>
</p>

<script>
    const $ = jQuery;
    const roles = [];
    let menuArray = [];
    let selectedRole = 'editor';
    const defaultRole = 'editor'
    const table = document.querySelector("#table-body");
    let wpApiSettings = {
        root: $("#root").val(),
        nonce: $("#nonce").val(),
    }
    axios.defaults.headers.common['X-WP-Nonce'] = wpApiSettings.nonce;
    axios.defaults.headers.common['Content-Type'] = 'application/json';

    // Fetch roles
    axios.get('/wp-json/hide-menu/v1/roles').then(res => {
        const {
            data
        } = res;
        const select = document.querySelector('#select-role');
        for (var key of Object.keys(data)) {
            roles[key] = data[key];
            select.options.add(new Option(data[key], key));
        }
    })

    // Trigger when role selected
    $(document).on('change', '#select-role', () => {
        table.innerHTML = '';
        const role = $("#select-role").val();
        selectedRole = role;
        fetchMenus(role);
    });

    function fetchMenus(role) {
        $('.role-spinner').fadeIn(200);
        axios.get(`/wp-json/hide-menu/v1/menus?role=${role}`).then(res => {
            const {
                data
            } = res;
            menuArray = data['default_config'];
            renderDataInTheTable(data);
            $('.role-spinner').fadeOut(200);
        });
    }

    function renderDataInTheTable(data) {
        const defaultData = data['default_config'];
        const roleData = data['role_config'];

        let menuTemp = roleData || defaultData;
        table.innerHTML = ''; // Reset
        menuArray = menuTemp; // Reset
        menuTemp.map((menu, index) => {
            let newRow = document.createElement("tr");

            if (menuTemp[index].value[0]) {
                // Col 1

                let th = document.createElement("th");
                th.setAttribute("scope", "row");
                let label = document.createElement("label");
                label.setAttribute("for", menuTemp[index].value[5]);

                if (iconChecker(menuTemp[index].value[6])) {
                    label.innerHTML =
                        `<span class="dashicons ${menuTemp[index].value[6]}" aria-hidden="true"></span> ${menuTemp[index].value[0]}`;
                } else {
                    label.innerHTML =
                        `<span class="dashicons" style="background-image: url(${menuTemp[index].value[6]})" aria-hidden="true"></span> ${menuTemp[index].value[0]}`;
                }

                th.appendChild(label);

                newRow.appendChild(th);

                // Col 2
                let td = document.createElement("td");

                let label2 = document.createElement("label");
                label2.classList.add("switch");

                let input = document.createElement("input");
                input.setAttribute("type", "checkbox");
                input.setAttribute("onchange", `toggleCheckbox(${index})`);
                if (selectedRole === 'administrator') input.setAttribute("disabled", ""); // Disable for admins

                if (menuTemp[index].isHidden) input.setAttribute("checked", "");

                th.setAttribute("name", menuTemp[index].value[5]);


                let span = document.createElement("span");
                span.classList.add("slider");
                span.classList.add("round");


                td.appendChild(label2);
                label2.appendChild(input);
                label2.appendChild(span);

                newRow.appendChild(td);
            }
            table.appendChild(newRow);

        });
    }

    function toggleCheckbox(index) {
        menuArray[index].isHidden = !menuArray[index].isHidden;
    }

    function iconChecker(icon) {
        return icon.includes('dashicons-');
    }

    function submit() {

        if (selectedRole === 'administrator') {
            return true;
        }
        $('.submit-spinner').fadeIn(200);
        const data = {
            selected_role: selectedRole,
            menu_config: menuArray
        };
        axios.post(`/wp-json/hide-menu/v1/menu?role=${selectedRole}`, data).then(res => {
            $('.submit-spinner').fadeOut(200);
        });
    }

    fetchMenus(defaultRole);
</script>

<?php
}

/**
 * API
 */
add_action('rest_api_init', function () {
    register_rest_route('hide-menu/v1', '/roles', array(
      'methods' => 'GET',
      'callback' => 'role_index',
    ));
    register_rest_route('hide-menu/v1', '/menus', array(
        'methods' => 'GET',
        'callback' => 'admin_menu_index',
      ));
    register_rest_route('hide-menu/v1', '/menu', array(
        'methods' => 'POST',
        'callback' => 'admin_menu_insert_update',
      ));
});

function role_index()
{
    global $wp_roles;
    if (! isset($wp_roles)) {
        $wp_roles = new WP_Roles();
    }
    return $wp_roles->get_names();
}

function admin_menu_index()
{
    $role = $_GET['role'];
    global $submenu, $menu, $pagenow;
    if (current_user_can('manage_options')) {
        $data = [
            'default_config'=>json_decode(get_option('hm_default_menu_list'), true),
            'role_config'=>json_decode(get_option('hm_'.$role.'_menu_list'), true)
        ];
        return $data;
    }
    return [];
}

function admin_menu_insert_update(WP_REST_Request $request)
{
    $body = json_decode($request->get_body());
    $body_params = $request->get_body_params();

    $selectedRole = $body->selected_role;
    $menuConfig = $body->menu_config;

    $optionName = 'hm_'.$selectedRole.'_menu_list';

    if (empty(get_option($optionName))) {
        add_option($optionName, json_encode($menuConfig), '');
    } else {
        update_option($optionName, json_encode($menuConfig), '');
    }

    return ['status'=>'ok'];
}
