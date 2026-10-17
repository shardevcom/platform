plugins {
    id("org.openjfx.javafxplugin")
}

javafx {
    version = "21.0.4"
    modules = listOf("javafx.controls")
}

dependencies {
    add("implementation", project(":packages:plugin-kit"))
    add("implementation", project(":packages:core"))
    add("implementation", "com.google.code.gson:gson:2.11.0")
}
