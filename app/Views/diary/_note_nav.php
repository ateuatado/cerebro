<nav class="nav nav-pills gap-2 mb-3" aria-label="Tipos de fichamento">
    <a class="nav-link<?= $collection === 'documentary' ? ' active' : '' ?>" href="<?= base_url('caderno/fichamentos') ?>">Fichas documentais</a>
    <a class="nav-link<?= $collection === 'bibliographic' ? ' active' : '' ?>" href="<?= base_url('caderno/fichamentos/bibliograficos') ?>">Notas bibliográficas</a>
</nav>
