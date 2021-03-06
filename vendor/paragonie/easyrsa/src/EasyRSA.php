<?php
namespace ParagonIE\EasyRSA;

// PHPSecLib:
use \phpseclib\Crypt\RSA;
// defuse/php-encryption:
use \ParagonIE\ConstantTime\Base64;
use \Defuse\Crypto\Key;
use \Defuse\Crypto\Crypto;
// Typed Exceptions:
use \ParagonIE\EasyRSA\Exception\InvalidChecksumException;
use \ParagonIE\EasyRSA\Exception\InvalidCiphertextException;

class EasyRSA implements EasyRSAInterface
{
    const SEPARATOR = '$';
    const VERSION_TAG = "EzR2";

    /**
     * KEM+DEM approach to RSA encryption.
     *
     * @param string $plaintext
     * @param PublicKey $rsaPublicKey
     *
     * @return string
     */
    public static function encrypt($plaintext, PublicKey $rsaPublicKey)
    {
        // Random encryption key
        $random_key = random_bytes(32);

        // Use RSA to encrypt the random key
        $rsaOut = self::rsaEncrypt($random_key, $rsaPublicKey);

        // Generate a symmetric key from the RSA output and plaintext
        $symmetricKey = hash_hmac(
            'sha256',
            $rsaOut,
            $random_key,
            true
        );
        $ephemeral = self::defuseKey(
            $symmetricKey
        );

        // Now we encrypt the actual message
        $symmetric = Base64::encode(
            Crypto::encrypt($plaintext, $ephemeral, true)
        );

        $packaged = \implode(self::SEPARATOR,
            array(
                self::VERSION_TAG,
                Base64::encode($rsaOut),
                $symmetric
            )
        );

        $checksum = \substr(
            \hash('sha256', $packaged),
            0,
            16
        );

        // Return the ciphertext
        return $packaged . self::SEPARATOR . $checksum;
    }

    /**
     * KEM+DEM approach to RSA encryption.
     *
     * @param string $ciphertext
     * @param PrivateKey $rsaPrivateKey
     *
     * @return string
     * @throws InvalidCiphertextException
     * @throws InvalidChecksumException
     */
    public static function decrypt($ciphertext, PrivateKey $rsaPrivateKey)
    {
        $split = explode(self::SEPARATOR, $ciphertext);
        if (\count($split) !== 4) {
            throw new InvalidCiphertextException('Invalid ciphertext message');
        }
        if (!\hash_equals($split[0], self::VERSION_TAG)) {
            throw new InvalidCiphertextException('Invalid version tag');
        }
        $checksum = \substr(
            \hash('sha256', implode('$', array_slice($split, 0, 3))),
            0,
            16
        );
        if (!\hash_equals($split[3], $checksum)) {
            throw new InvalidChecksumException('Invalid checksum');
        }

        $rsaCipher = Base64::decode($split[1]);
        $rsaPlain = self::rsaDecrypt(
            $rsaCipher,
            $rsaPrivateKey
        );
        $symmetricKey = hash_hmac(
            'sha256',
            $rsaCipher,
            $rsaPlain,
            true
        );

        $key = self::defuseKey($symmetricKey);
        return Crypto::decrypt(
            Base64::decode($split[2]),
            $key,
            true
        );
    }

    /**
     * Sign with RSASS-PSS + MGF1+SHA256
     *
     * @param string $message
     * @param PrivateKey $rsaPrivateKey
     * @return string
     */
    public static function sign($message, PrivateKey $rsaPrivateKey)
    {
        static $rsa = null;
        if (!$rsa) {
            $rsa = new RSA();
            $rsa->setSignatureMode(RSA::SIGNATURE_PSS);
            $rsa->setMGFHash('sha256');
        }

        $rsa->loadKey($rsaPrivateKey->getKey());
        return $rsa->sign($message);
    }

    /**
     * Verify with RSASS-PSS + MGF1+SHA256
     *
     * @param string $message
     * @param string $signature
     * @param PublicKey $rsaPublicKey
     * @return bool
     */
    public static function verify($message, $signature, PublicKey $rsaPublicKey)
    {
        static $rsa = null;
        if (!$rsa) {
            $rsa = new RSA();
            $rsa->setSignatureMode(RSA::SIGNATURE_PSS);
            $rsa->setMGFHash('sha256');
        }

        $rsa->loadKey($rsaPublicKey->getKey());
        return $rsa->verify($message, $signature);
    }

    /**
     * Decrypt with RSAES-OAEP + MGF1+SHA256
     *
     * @param string $plaintext
     * @param PublicKey $rsaPublicKey
     * @return string
     * @throws InvalidCiphertextException
     */
    protected static function rsaEncrypt($plaintext, PublicKey $rsaPublicKey)
    {
        static $rsa = null;
        if (!$rsa) {
            $rsa = new RSA();
            $rsa->setEncryptionMode(RSA::ENCRYPTION_OAEP);
            $rsa->setMGFHash('sha256');
        }

        $rsa->loadKey($rsaPublicKey->getKey());
        return $rsa->encrypt($plaintext);
    }

    /**
     * Decrypt with RSAES-OAEP + MGF1+SHA256
     *
     * @param string $ciphertext
     * @param PrivateKey $rsaPrivateKey
     * @return string
     * @throws InvalidCiphertextException
     */
    protected static function rsaDecrypt($ciphertext, PrivateKey $rsaPrivateKey)
    {
        static $rsa = null;
        if (!$rsa) {
            $rsa = new RSA();
            $rsa->setEncryptionMode(RSA::ENCRYPTION_OAEP);
            $rsa->setMGFHash('sha256');
        }

        $rsa->loadKey($rsaPrivateKey->getKey());

        $return = @$rsa->decrypt($ciphertext);
        if ($return === false) {
            throw new InvalidCiphertextException('Decryption failed');
        }
        return $return;
    }

    /**
     * Use an internally generated key in a Defuse context
     *
     * @param string $randomBytes
     * @return Key
     */
    protected static function defuseKey($randomBytes)
    {
        $kludege = new Kludge();
        return $kludege->defuseKey($randomBytes);
    }
}
