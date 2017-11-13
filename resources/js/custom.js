/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
jQuery(document).ready(function($)
{
    $("#cs_facebooklogin").click(function()
    {
        ajaxurl     =   control_vars.ajaxurl;
        login_type  =   'facebook';
        csfl_id = $("#csfl_id").val();
        jQuery.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        data: {
                            'action'            :   'csfl_ajax_facebook_login',
                            'login_type'        :   login_type,
                            'csfl_id'           :   csfl_id

                        },
                        success: function (data) {
                            window.location.href = data;
                        },
                        error: function (errorThrown) {

                        }
                    });//end ajax
    });
});

