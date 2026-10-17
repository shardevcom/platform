plugins {
    application
    id("org.openjfx.javafxplugin")
}

javafx {
    version = "21.0.4"
    modules = listOf("javafx.controls")
}

application {
    mainClass = "dev.shardev.apps.admin.Main"
}

dependencies {
    add("implementation", project(":packages:core"))
    add("implementation", project(":packages:plugin-kit"))
    add("implementation", project(":plugins:product"))
}
