<?php

/**
 * Plugin Name: Product Custom Plugin
 * Plugin URI : https://wwww.pluginuri@example.com
 * Author: Divyank Pandey
 * Author URI : https://wwww.phpdev@example.com
 * Version : 1.0
 */


if (!defined('ABSPATH')) {
    exit();
}

//defining plugin path and url
define('CUSTOM_SETTING_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CUSTOM_SETTING_PLUGIN_URL', plugin_dir_url(__FILE__));

//register custom post type products
add_action('init', 'register_product_post_type');
function register_product_post_type()
{
    $args = array(
        'labels' => array(
            'name' => 'Product',
            'singular_name' => 'Product',
            'plural_name' => 'Products',
            'add_item' => 'Add Product',
            'add_new_item' => 'Add New Product',
        ),
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-products'
    );

    register_post_type('products_cpt', $args);
}

add_action('wp_enqueue_scripts', 'enqueue_assets');
function enqueue_assets()
{
    wp_enqueue_script('jquery');
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');
    wp_enqueue_script('my-custom-js', CUSTOM_SETTING_PLUGIN_URL . 'assets/js/my_js.js', array('jquery'), '1.0', true);
    wp_enqueue_script('import-csv-js', CUSTOM_SETTING_PLUGIN_URL . 'assets/js/import_csv.js', array('jquery'), '1.0', true);
    wp_enqueue_script('sweetalert-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js', array('jquery'), null, true);
    wp_enqueue_style('my-custom-css', CUSTOM_SETTING_PLUGIN_URL . 'assets/css/custom-styles.css');
    wp_localize_script('my-custom-js', 'ajaxObject', array('ajaxUrl' => admin_url('admin-ajax.php')));
    wp_localize_script('import-csv-js', 'ajaxObject', array('ajaxUrl' => admin_url('admin-ajax.php')));
    wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyACpdPms1DFKXdVvaK0PpfrnFoGGbmKyyg&loading=async&callback=initMap', [], null, true);
    wp_enqueue_script('map', CUSTOM_SETTING_PLUGIN_URL . '/assets/js/map.js', array(), [], true);
}

//shortcode to add html input form for add_product_page
add_shortcode('add_wc_variable_product', 'add_wc_variable_product_page');
function add_wc_variable_product_page()
{
    ob_start();
    require CUSTOM_SETTING_PLUGIN_PATH . 'view/add_wc_variable_product_page.php';
    return ob_get_clean();
}


add_filter('woocommerce_get_image_size_single', function ($size) {

    $new_size
        = array(
            'width'  => 500,
            'height' => 400,
            'crop'   => 0,
        );
    return $new_size;
});



add_theme_support(
    "woocommerce",
    array(
        'single_image_width' => 400
    )
);

//to enable flexslider sliding
add_filter('woocommerce_single_product_carousel_options', 'custom_woo_flexslider_options');
function custom_woo_flexslider_options($options)
{
    // Enable automatic slideshow
    $options['slideshow'] = true;
    $options['animationLoop'] = true;
    $options['slideshowSpeed'] = 2000;
    return $options;
}

//ajax to create woocommerce product
add_action("wp_ajax_create_wc_product", "create_wc_product");
add_action("wp_ajax_nopriv_create_wc_product", "create_wc_product");
function create_wc_product()
{
    if ($_SERVER['REQUEST_METHOD'] == "POST") {
        $productName = isset($_POST['productName']) ? sanitize_textarea_field($_POST['productName']) : '';
        $productPrice = isset($_POST['productPrice']) ? intval($_POST['productPrice']) : '';
        $productHeight = isset($_POST['productHeight']) ? intval($_POST['productHeight']) : '';
        $productWeight = isset($_POST['productWeight']) ? intval($_POST['productWeight']) : '';
        $productWidth = isset($_POST['productWidth']) ? intval($_POST['productWidth']) : '';
        $productDepth = isset($_POST['productDepth']) ? intval($_POST['productDepth']) : '';
        $productDescription = isset($_POST['productDescription']) ? (wp_kses_post(($_POST['productDescription']))) : '';
        $featured_image_key = isset($_POST['featured_image_key']) ? intval($_POST['featured_image_key']) : '';



        if (empty($productName) || empty($productPrice) || empty($productWeight) || empty($productHeight) || empty($productWidth) || empty($productDescription)) {
            wp_send_json_error(array('message' => "Error : Please fill all details."));
            wp_die();
        }

        if (empty($featured_image_key)) {
            wp_send_json_error(array('message' => "Error : Please Select featured image from selected images."));
            wp_die();
        }

        $product = new WC_Product_Variable();
        $product->set_name($productName);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_price($productPrice);
        $product->set_height($productHeight);
        $product->set_width($productWidth);
        $product->set_weight($productWeight);
        $product->set_length($productDepth);
        $product->set_regular_price($productPrice);
        $product->set_sale_price($productPrice);
        $product->set_manage_stock(true);
        $product->set_stock_quantity(10);
        $product->set_description($productDescription);

        if (empty($_FILES['productImages']['name'][0])) {
            wp_send_json_error(array('message' => "Error : Please Select Images."));
            wp_die();
        } else {
            $acceptedFormat = array("jpeg", "jpg", "png");
            $filesType = $_FILES['productImages']['type'];
            foreach ($filesType as $fileType) {
                $filetype = end(explode('/', $fileType));
                if (!in_array($filetype, $acceptedFormat)) {
                    wp_send_json_error(array('message' => "Error : Selected Image file type " . $fileType . " Please Select Images only JPEG , JPG and PNG."));
                    wp_die();
                }
            }
        }

        $upload_dir = wp_upload_dir();
        $attchmentIds = [];
        foreach ($_FILES['productImages']['tmp_name'] as $key => $fileTmpName) {

            if ($fileTmpName) {
                $imageName = $_FILES['productImages']['name'][$key];
                $imageData = file_get_contents($fileTmpName);
                $upload_path = $upload_dir['path'] . '/' . $imageName;
                $imageType
                    = $_FILES['productImages']['type'][$key];
                if (file_put_contents($upload_path, $imageData) !== FALSE) {

                    $attachment         =   array(
                        'post_mime_type' => $imageType,
                        'post_title'     =>  $imageName,
                        'post_status'    => 'inherit',
                        'guid'           => $upload_dir['url'] . '/' . $imageName
                    );

                    $attachment_id = wp_insert_attachment($attachment, $upload_path);

                    $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_path);
                    wp_update_attachment_metadata($attachment_id, $attachment_data);

                    if ($key == $featured_image_key) {
                        // echo "Condition true for key '$key' Featured image key '$featured_image_key' \n";
                        // print_r($attachment_id . "\n");
                        $product->set_image_id($attachment_id);
                    } else {
                        // echo $attachment_id . "\n";
                        $attchmentIds[] = $attachment_id;
                    }
                    $product->set_gallery_image_ids($attchmentIds);
                }
            }
        }
        $product->save();
        $productId = $product->get_id();
        if ($productId) {
            wp_send_json_success(array('message' => "Success : Product created successfully"));
        }
    }

    wp_die();
}


//code to add visual text editor on front-end input-form
function display_product_description_form()
{

    $content = '';

    $editor_settings = array(
        'media_buttons' => FALSE,
        'textarea_name' => 'productDescription',
        'textarea_rows' => 10,
        'teeny' => false,
        'quicktags' => true,
    );


    wp_editor($content, 'productDescription', $editor_settings);
}
add_shortcode('product_description_form', 'display_product_description_form');


add_action('wp_ajax_import_csv', 'import_csv');
function import_csv()
{
    if (!isset($_FILES['csv_file'])) {
        wp_send_json_error(['message' => 'No file uploaded.']);
        wp_die();
    }

    $csv_file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($csv_file, 'r')) === false) {
        wp_send_json_error(['message' => 'Could not open CSV file.']);
        wp_die();
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        wp_send_json_error(['message' => 'Invalid CSV file.']);
        wp_die();
    }

    $products_updated = 0;
    $products_created = 0;
    while (($data = fgetcsv($handle)) !== false) {
        //$data = array_combine($headers, $row);
        $sku = $data[11];
        global $wpdb;
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pm 
            ON p.ID = pm.post_id
            WHERE p.post_type = 'product' 
            AND pm.meta_key = '_sku' 
            AND pm.meta_value = %s
        ", $sku));


        if ($product_id) {
            $product = wc_get_product($product_id);
            $products_updated++;
        } else {

            $product = new WC_Product_Variable();
            $products_created++;
        }

        $product->set_name($data[0]);
        $product->set_description($data[1]);
        $product->set_price($data[2]);
        $product->set_regular_price($data[2]);
        $product->set_height($data[3]);
        $product->set_width($data[4]);
        $product->set_length($data[5]);
        $product->set_weight($data[6]);
        $product->set_status('publish');
        $product->set_stock_quantity(10);
        $product->set_manage_stock(true);
        $product->set_sku($data[11]);

        $upload_dir = wp_upload_dir();
        $galleryImages = [];

        for ($i = 7; $i <= 10; $i++) {

            if (!empty($data[$i])) {

                $featured_image_path = $upload_dir['path'] . '/' . $data[$i];
                $image_extension = pathinfo($data[$i], PATHINFO_EXTENSION);
                $image_type = 'image/' . $image_extension;

                $attachment =   array(
                    'post_mime_type' => $image_type,
                    'post_title'     =>  $data[$i],
                    'post_status'    => 'inherit',
                    'guid'           => $upload_dir['url'] . '/' . $data[$i]
                );

                $attachment_id = wp_insert_attachment($attachment, $featured_image_path);
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $featured_image_path);
                wp_update_attachment_metadata($attachment_id, $attachment_data);

                if ($i == 7) {
                    $product->set_image_id($attachment_id);
                } else {
                    $galleryImages[] = $attachment_id;
                }
            }
        }

        if (!empty($galleryImages)) {
            $product->set_gallery_image_ids($galleryImages);
        }

        $product_id = $product->save();
    }

    fclose($handle);

    wp_send_json_success([
        'message' => "$products_created products were created, and $products_updated products were updated successfully."
    ]);
}
