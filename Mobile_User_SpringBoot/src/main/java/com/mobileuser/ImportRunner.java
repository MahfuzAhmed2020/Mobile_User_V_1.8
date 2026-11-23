package com.mobileuser;
import org.springframework.stereotype.Component;
import org.springframework.context.annotation.Profile;
import org.springframework.boot.ApplicationRunner;
import org.springframework.boot.ApplicationArguments;
import org.springframework.core.env.Environment;
import com.fasterxml.jackson.databind.ObjectMapper;
import java.nio.file.*;
@Component @Profile("legacy-import")
public class ImportRunner implements ApplicationRunner {
 private final LegacyImporter importer;private final ObjectMapper json;private final Environment env;
 public ImportRunner(LegacyImporter importer,ObjectMapper json,Environment env){this.importer=importer;this.json=json;this.env=env;}
 public void run(ApplicationArguments args) throws Exception {
  String path=env.getProperty("IMPORT_FILE");if(path==null)throw new IllegalArgumentException("Set IMPORT_FILE to a reviewed allowlisted export");
  var result=importer.importData(json.readTree(Files.readString(Path.of(path))));System.out.println("Import committed: "+result.get("new_accounts")+" new accounts; source "+result.get("source_key"));
 }
}
