//Needed libraries
#include <OneWire.h>
#include <DallasTemperature.h>
#include <Ethernet.h>
#include <SPI.h>
#include <dht.h>

//Data wire for the temp sensors is plugged into pin 8 on the Arduino
#define ONE_WIRE_BUS 8

dht DHT;

#define DHT11_PIN 3

//Ethernet settings
byte mac[] = { 0x90, 0xA2, 0xDA, 0x0D, 0x9D, 0x12 }; 
char server[] = "arduino1.danodemano.com";

EthernetClient client; //Create the client as an EthernetClient
int numSensors = 8; //Number of temp sensors
float temparray[8]; //The array for the temps
int i = 0; //For tracking the loop

//Setup a oneWire instance to communicate with the OneWire devices
OneWire oneWire(ONE_WIRE_BUS);

//Pass our oneWire reference to Dallas Temperature. 
DallasTemperature sensors(&oneWire);

//Addresses for the OneWire sensors
DeviceAddress Thermostat = { 0x28, 0x6A, 0x55, 0x47, 0x04, 0x00, 0x00, 0xAD };
DeviceAddress Norbert1 = { 0x28, 0x2E, 0xEF, 0x74, 0x05, 0x00, 0x00, 0x9D };
DeviceAddress Norbert2 = { 0x28, 0xD2, 0x44, 0x74, 0x05, 0x00, 0x00, 0xD1 };
DeviceAddress Outside = { 0x28, 0xF5, 0x52, 0x47, 0x04, 0x00, 0x00, 0x69 };
DeviceAddress LagerFridge = { 0x28, 0xEC, 0x23, 0x47, 0x04, 0x00, 0x00, 0x04 };
DeviceAddress FurnaceRoom = { 0x28, 0x52, 0x3D, 0x74, 0x05, 0x00, 0x00, 0xA5 };
DeviceAddress DanRoom = { 0x28, 0x0C, 0xCA, 0x74, 0x05, 0x00, 0x00, 0x6F };
DeviceAddress Dubias = { 0x28, 0xBE, 0xA5, 0x74, 0x05, 0x00, 0x00, 0x78 };

//Variables for tracking a power outage
int pm_pin = 2; //The digital pin for power monitoring
int pm_val = 0; //An initial value for the digital pin

//Variable for ensuring connections to the server
bool connected = false; 

void setup(void)
{
  //start serial port
  Serial.begin(9600);
  //Serial.begin(115200);
  
  //Start up the library
  sensors.begin();
  
  //set the resolution to 10 bit for all the sensors
  sensors.setResolution(Thermostat, 10);
  sensors.setResolution(Norbert1, 10);
  sensors.setResolution(Norbert2, 10);
  sensors.setResolution(Outside, 10);
  sensors.setResolution(LagerFridge, 10);
  sensors.setResolution(FurnaceRoom, 10);
  sensors.setResolution(DanRoom, 10);
  sensors.setResolution(Dubias, 10);
  
  //start the Ethernet connection:
  if (Ethernet.begin(mac) == 0) {
    Serial.println("Failed to configure Ethernet using DHCP");
    //no point in carrying on, so do nothing forevermore:
    for(;;)
      ;
  }
  else
  {
  Serial.println(Ethernet.localIP());
  } //end if (Ethernet.begin(mac) == 0) {
  
  delay(1000); // give the Ethernet shield a second to initialize
  
  pinMode(pm_pin, INPUT); //Make sure we set the power monitoring pin as an INPUT
} //end void setup(void)

void printTemperature(DeviceAddress deviceAddress)
{
  float tempC = sensors.getTempC(deviceAddress);
  //getTempC returns -127.00 if no respoce or invalid responce from sensors
  if (tempC == -127.00) {
    Serial.print("Error getting temperature");
  } else {
    //Output the temps for debugging
    Serial.print("C: ");
    Serial.print(tempC);
    Serial.print(" F: ");
    Serial.print(DallasTemperature::toFahrenheit(tempC));
  } //end if (tempC == -127.00) {
} //end void printTemperature(DeviceAddress deviceAddress)

void loop(void)
{ 
  if(!connected)   {
  Serial.println("Not connected"); 
  } //end if(!connected) 
 
  if (client.connect(server, 80)){
  connected = true;    
  Serial.println("Connected!"); 
    
    //Get the digital reading for the power status - 1 = power on, 0 = power off
    pm_val = digitalRead(pm_pin);
    Serial.print("Power status: ");
    Serial.println(pm_val);
  
  Serial.print("Getting digital temperatures...\n\r");
  sensors.requestTemperatures();
  
  Serial.print("Thermostat temperature is: ");
  printTemperature(Thermostat);
  Serial.print("\n\r");
  Serial.print("Norbert basking temperature is: ");
  printTemperature(Norbert1);
  Serial.print("\n\r");
  Serial.print("Norbert cool side temperature is: ");
  printTemperature(Norbert2);
  Serial.print("\n\r");
  Serial.print("Outside temperature is: ");
  printTemperature(Outside);
  Serial.print("\n\r");
  Serial.print("Lager fridge temperature is: ");
  printTemperature(LagerFridge);
  Serial.print("\n\r\n\r");
  Serial.print("Furnace room temperature is: ");
  printTemperature(FurnaceRoom);
  Serial.print("\n\r\n\r");
  Serial.print("Dan room temperature is: ");
  printTemperature(DanRoom);
  Serial.print("\n\r\n\r");
  Serial.print("Dubia temperature is: ");
  printTemperature(Dubias);
  Serial.print("\n\r\n\r");
  
    // READ DATA
  Serial.print("DHT22, \t");
  int chk = DHT.read22(DHT11_PIN);
  switch (chk)
  {
    case DHTLIB_OK:  
		Serial.print("OK,\t"); 
		break;
    case DHTLIB_ERROR_CHECKSUM: 
		Serial.print("Checksum error,\t"); 
		break;
    case DHTLIB_ERROR_TIMEOUT: 
		Serial.print("Time out error,\t"); 
		break;
    default: 
		Serial.print("Unknown error,\t"); 
		break;
  }
 // DISPLAY DATA
  Serial.print(DHT.humidity,1);
  Serial.print(",\t");
  Serial.println(DHT.temperature,1);
  
  //Store the temps into an array for uploading to the server
  temparray[0] = sensors.getTempC(Norbert1);
  temparray[1] = sensors.getTempC(Thermostat);
  temparray[2] = sensors.getTempC(LagerFridge);
  //temparray[3] = sensors.getTempC(Outside);
  temparray[3] = DHT.temperature;
  temparray[4] = sensors.getTempC(Norbert2);
  temparray[5] = sensors.getTempC(FurnaceRoom);
  //temparray[5] = DHT.temperature;
  temparray[6] = sensors.getTempC(DanRoom);
  temparray[7] = sensors.getTempC(Dubias);
  

 client.print("GET /write.php?");
 Serial.print("GET /write.php?");     
 for (i=0; i<numSensors; i++)
 {
 client.print("t");
 Serial.print("t");
 client.print(i);
 Serial.print(i);
 client.print("=");
 Serial.print("=");
 client.print(temparray[i]);
 Serial.print(temparray[i]);
 if (i < numSensors-1)
 {
 client.print("&&");
 Serial.print("&&");
 }
 else
 {
   //There is no else here
 } //end if (i < numSensors-1)  
 
 } //end for (i=0; i<numSensors; i++)
 
  //Adding the power status into the mix
 client.print("&&");
 Serial.print("&&");
 client.print("p");
 Serial.print("p");
 client.print("=");
 Serial.print("=");
 client.print(pm_val);
 Serial.print(pm_val);
 
 //Adding the humidity into the mix
 client.print("&&");
 Serial.print("&&");
 client.print("h3");
 Serial.print("h3");
 client.print("=");
 Serial.print("=");
 client.print(DHT.humidity);
 Serial.print(DHT.humidity);
 
  //All the needed HTML headers to ensure a good connection
 client.println(" HTTP/1.1");
 Serial.println(" HTTP/1.1");
 client.println("Host: arduino1.danodemano.com");
 Serial.println("Host: arduino1.danodemano.com");
 client.println("User-Agent: Arduino");
 Serial.println("User-Agent: Arduino");
 client.println("Accept: text/html");
 Serial.println("Accept: text/html");
 client.println("Connection: close");
 Serial.println("Connection: close");
 client.println();
 Serial.println();
  
  client.stop();
 connected = false; 
  
  }
  else
  {
  Serial.println("Cannot connect to Server");         // else block if the server connection fails (debugging)
  } //end if (client.connect(server, 80))
  
  Serial.println("Sleeping for 30 seconds");
  delay(30000); //Delay for 30 seconds then do it again
} //end void loop(void)
