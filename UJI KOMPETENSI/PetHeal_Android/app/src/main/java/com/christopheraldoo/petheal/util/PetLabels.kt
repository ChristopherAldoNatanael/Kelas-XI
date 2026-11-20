package com.christopheraldoo.petheal.util

/**
 * Terjemahan tampilan untuk atribut hewan.
 *
 * Nilai di server TETAP kanonik ("Dog", "Male") karena dipakai untuk ikon
 * spesies, filter, dan kompatibilitas data lama. Yang diterjemahkan hanya
 * teks yang dibaca pengguna, supaya seluruh layar memakai satu istilah
 * Indonesia — sekaligus membuat pencarian bisa memakai kata Indonesia.
 */
private val SpeciesLabels = mapOf(
    "dog" to "Anjing",
    "cat" to "Kucing",
    "bird" to "Burung",
    "rabbit" to "Kelinci",
    "other" to "Lainnya"
)

private val GenderLabels = mapOf(
    "male" to "Jantan",
    "female" to "Betina"
)

/** "Dog" -> "Anjing". Nilai tak dikenal dikembalikan apa adanya. */
fun speciesLabelOf(value: String?): String =
    SpeciesLabels[value?.trim()?.lowercase()] ?: value?.trim().orEmpty()

/** "Male" -> "Jantan". Nilai tak dikenal dikembalikan apa adanya. */
fun genderLabelOf(value: String?): String =
    GenderLabels[value?.trim()?.lowercase()] ?: value?.trim().orEmpty()
