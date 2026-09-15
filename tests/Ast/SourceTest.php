<?php

use Heritage\Contracts\Auth\MustVerifyEmail;
use Ugarit\Chisel\Ast\Source;
use Ugarit\Chisel\Chisel;

it('removes imports interfaces and traits from php files on destruct', function (): void {
    $path = $this->tempDir.'/User.php';

    file_put_contents($path, <<<'PHP'
<?php

namespace App\Models;

use Heritage\Database\Eloquent\Model, Heritage\Contracts\Auth\MustVerifyEmail;
use Ugarit\Fortify\TwoFactorAuthenticatable;
use Ugarit\Sanctum\HasApiTokens;
use Tests\Fixtures\HasFactory;

class User extends Model implements MustVerifyEmail
{
    use TwoFactorAuthenticatable, HasApiTokens;
    use HasFactory;

    protected $table = 'users';
}
PHP);

    $file = Chisel::in($this->tempDir)
        ->php('User.php')
        ->removeImport(MustVerifyEmail::class)
        ->removeTrait('TwoFactorAuthenticatable')
        ->removeTrait('HasFactory')
        ->removeInterface('MustVerifyEmail');

    unset($file);

    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('use Heritage\Database\Eloquent\Model;')
        ->toContain('use HasApiTokens;')
        ->not->toContain('implements MustVerifyEmail')
        ->not->toContain('use TwoFactorAuthenticatable,')
        ->not->toContain('    use HasFactory;');
});

it('removes imports from files without a namespace declaration', function (): void {
    $path = $this->tempDir.'/file.php';

    file_put_contents($path, <<<'PHP'
<?php

use Foo\Bar;
use Baz\Qux;

class X {}
PHP);

    (new Source($path))->removeImport('Bar')->save();

    expect(file_get_contents($path))
        ->not->toContain('use Foo\Bar;')
        ->toContain('use Baz\Qux;');
});

it('can save a php file with no queued edits', function (): void {
    $path = $this->tempDir.'/SampleClass.php';

    copy(dirname(__DIR__).'/fixtures/php/SampleClass.php.stub', $path);

    $original = file_get_contents($path);

    (new Source($path))->save();

    expect(file_get_contents($path))->toBe($original);
});
