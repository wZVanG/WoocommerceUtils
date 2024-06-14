<?php

if (!defined('ABSPATH')) exit;

function wau_submenu_settings_callback(){
     ?>

    <div class="wrap woocommerce">
        <h1>Woo Advanced Utils</h1>
        <hr>
        <h2 class="nav-tab-wrapper">
            <a href="?page=wau_settings&tab=docs" class="nav-tab <?php
            if ((isset($_REQUEST['tab'])) && ($_REQUEST['tab'] == "docs")) {
                print " nav-tab-active";
            } ?>">Documentación</a>

            <a href="?page=wau_settings&tab=settings" class="nav-tab <?php
            if ((!isset($_REQUEST['tab'])) || ($_REQUEST['tab'] == "settings")) {
                print " nav-tab-active";
            } ?>">Ajustes</a>

        </h2>
        <?php
        if ((!isset($_REQUEST['tab'])) || ($_REQUEST['tab'] == "settings")) {
            wau_submenu_settings_settings();
        } elseif ($_REQUEST['tab'] == "docs") {
            wau_submenu_settings_docs();
        } ?>
    </div>
    <?php
}

function wau_submenu_settings_docs(){
    ?>
    <h1>Plugin para extender funcionalidades de WooCommerce</h1>
    <div>
        <div>
            <h3>Descripción</h3>
        </div>
        <div>
            <p>Plugins para extender más funciones que no están disponibles en Woocommerce</p>
        </div>
    </div>

    <?php

}

function wau_submenu_settings_settings()
{
    ?>
    <h1>Ajustes</h1>
    <form method="post" action="options.php" id="wau_formulario">
        <?php settings_fields('wau_settings_group'); ?>
        <?php do_settings_sections('wau_settings_group'); ?>

        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label>Habilitar Webhooks inmediatos</label>
                </th>
                <td class="forminp forminp-checkbox">
                    <input type="checkbox" name="wau_webhookasync_checkbox" id="wau_webhookasync_checkbox" value="on"
                        <?php if (esc_attr(get_option('wau_webhookasync_checkbox')) == "on") echo "checked"; ?> />
                </td>
            </tr>

			<tr valign="top">
				<th scope="row" class="titledesc">
					<label>Habilitar Endpoint personalizado</label>
				</th>
				<td class="forminp forminp-checkbox">
					<input type="checkbox" name="wau_customendpoint_checkbox" id="wau_customendpoint_checkbox" value="on"
						<?php if (esc_attr(get_option('wau_customendpoint_checkbox')) == "on") echo "checked"; ?> />
				</td>
			</tr>
            
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label>Formato</label>
                </th>
                <td class="forminp forminp-checkbox">
                    <select name="wau_format_checkbox" id="wau_format_checkbox">
                        <option value="vertical" <?php if (esc_attr(get_option('wau_format_checkbox')) == "vertical") echo "selected"; ?> >Vertical</option>
                        <option value="horizontal" <?php if (esc_attr(get_option('wau_format_checkbox')) == "horizontal") echo "selected"; ?> >Horizontal</option>
                    </select>
                </td>
            </tr>

            </tbody>
        </table>

        <?php submit_button('Guardar cambios'); ?>
    </form>
    <?php
}

