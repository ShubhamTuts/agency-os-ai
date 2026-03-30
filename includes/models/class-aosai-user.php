<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_User {
    use AOSAI_Singleton;

    public function get( int $user_id ): ?\WP_User {
        if ( empty( $user_id ) ) {
            return null;
        }
        return get_user_by( 'id', $user_id );
    }

    public function get_formatted_user( int $user_id ): ?array {
        $user = $this->get($user_id);
        if (!$user) {
            return null;
        }

        return array(
            'id' => $user->ID,
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'display_name' => $user->display_name,
            'avatar_url' => get_avatar_url($user->ID),
        );
    }

    public function update_profile( int $user_id, array $data ): bool|\WP_Error {
        $user_data = array(
            'ID' => $user_id,
        );

        if ( isset( $data['first_name'] ) ) {
            $user_data['first_name'] = sanitize_text_field( $data['first_name'] );
        }

        if ( isset( $data['last_name'] ) ) {
            $user_data['last_name'] = sanitize_text_field( $data['last_name'] );
        }

        if ( isset( $data['email'] ) ) {
            $email = sanitize_email( $data['email'] );
            if ( ! is_email( $email ) ) {
                return new \WP_Error( 'invalid_email', __( 'The email address is not valid.', 'agency-os-ai' ) );
            }
            if ( email_exists( $email ) && email_exists( $email ) != $user_id ) {
                return new \WP_Error( 'email_exists', __( 'This email address is already in use.', 'agency-os-ai' ) );
            }
            $user_data['user_email'] = $email;
        }
        
        if ( ! empty( $data['password'] ) ) {
            if ( strlen( $data['password'] ) < 8 ) {
                return new \WP_Error('password_too_short', __('Password must be at least 8 characters long.', 'agency-os-ai'));
            }
            wp_set_password( $data['password'], $user_id );
        }
        
        $result = wp_update_user( $user_data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }
}
